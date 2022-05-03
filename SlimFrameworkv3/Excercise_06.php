<?php

/**
 * @author Ragnar Storstrøm
 * @copyright 2022
 */

# Load the Slim framework:
require_once '../vendor/autoload.php';

# Add the ServerRequestInterface but rename it Request
use \Psr\Http\Message\ServerRequestInterface as Request;
# Add the ReponseInterface but rename it Response
use \Psr\Http\Message\ResponseInterface as Response;
# Add the Logger but rename it MonologLogger
use \Monolog\Logger as MonologLogger;
# Add the StreamHandler but rename it MonologStreamHandler
use \Monolog\Handler\StreamHandler as MonologStreamHandler;

# Disable libxml errors to allow us to define our own error messages
libxml_use_internal_errors(true);
# Create a hashtable to store configuration information
$config['displayErrorDetails'] = true;

# Add the database configuration to the configuration hashtable
$config['db']['host']   = 'localhost';
$config['db']['user']   = 'root';
$config['db']['pass']   = '';
$config['db']['dbname'] = 'classicmodels';

# Add the LDAP configuration to the configuration hashtable
$config['ldap']['uri']    = 'ldap://192.168.46.10:389';
$config['ldap']['usetls'] = false;
$config['ldap']['basedn'] = 'O=data';

# Create the application instance and configure it
$app = new \Slim\App(['settings' => $config]);

# Define a new global variable that will hold the container object
global $container;
# Store the container object in the global variable
$container = $app->getContainer();

# Make a new function named logger that returns a Monolog object
$container['logger'] = function($c)
{
# Create a new MonologLogger object and give it a name 
  $Logger      = new MonologLogger('DemoRESTAPI');
# Create a new MonologStreamHandler object and provide a log file path
  $FileHandler = new MonologStreamHandler("../logs/DemoRESTAPI.log");
# Connect the MonologLogger object with the MonologStreamHandler object
  $Logger->pushHandler($FileHandler);
  return $Logger;
};

# Make a new function named db that returns a PDO object for MySQL
$container['db'] = function ($c)
{
# Get the database settings from the configuration hashtable
  $settings = $c['settings']['db'];
# Create a new PDO object using the database settings
  $pdo      = new PDO('mysql:host=' . $settings['host'] . ';dbname=' . $settings['dbname'], $settings['user'], $settings['pass']);
# Set various database attributes
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
# Return the MySQL PDO object
  return $pdo;
};

# Make a new function named ldap that returns a LDAP connection object
$container['ldap'] = function ($c) {
# Get the LDAP settings from the configuration hashtable
  $ldap  = $c['settings']['ldap'];
# Do not bind yet
  $lbind = null;
# Create a LDAP connetion using the LDAP URI from the configuration
  $lconn = ldap_connect($ldap['uri']);
# If the connection was successful...
  if ($lconn)
  {
    # ...then set the LDAP options to use
    ldap_set_option($lconn, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($lconn, LDAP_OPT_PROTOCOL_VERSION, 3);
  } else
  {
    # ...else we don't have a connection 
    $lconn = null;
  }
# Return the LDAP connection object
  return $lconn;
};

# Define a new endpoint and the response for a GET request
$app->get('/', function (Request $Request, Response $Reponse)
  {
    # Return a message for anyone that reaches this REST endpoint
    $Reponse->getBody()->write("This is the REST API that allows REST access to a database server");
    return $Reponse;
  }
);

# Add a new endpoint group called v1
# Add a new endpoint group called v1
$app->group('/v1', function ()
  {
    # Add a new endpoint under the v1 group named Customers, accessible using the GET methoc
    $this->get('/Customers', function (Request $Request, Response $Response, $Args)
      {
        # Log to file which REST endpoint was reached and which method was used
        $this->logger->debug("Endpoint /Customers reached with method GET");

        # Get the database connection object 
        $PDOObject    = $this->get('db');

        # Prepare a SQL query to execute
        $SQLStatement = $PDOObject->prepare("SELECT customerNumber, customerName FROM customers");
        # Execure the query
        $QueryRes     = $SQLStatement->execute();

        # If the query was successful...
        if ($QueryRes === true)
        {
          # ...then get all of the records from the query
          $Result = $SQLStatement->fetchAll();
          # Convert the objects to JSON format and return a 200 - OK error code
          return $Response->withJson($Result, 200);
        } else
        {
          # ...else get the error information
          $Errors = $PDOObject->errorInfo();
          # Convert the error information to JSON format and return a 400 - Bad request error code
          return $Response->withJson($Errors, 400);
        }
      });

    # Add a new endpoint group under v1 called Customer 
    $this->group('/Customer', function()
      {
        # Add a new endpoint accessible using GET with a parameter named CustomerNumber
        $this->get('/{CustomerNumber}', function (Request $Request, Response $Response, $Args)
          {
            # Get the parameter value
            $CustomerNumber = html_entity_decode($Args['CustomerNumber']);

            # Log to file which REST endpoint was reached and which method was used
            $this->logger->debug("Endpoint /Customer/{CustomerNumber} reached with method GET and value $CustomerNumber");
            
            # Get the database connection object 
            $PDOObject      = $this->get('db');
    
            # Prepare a SQL query to execute
            $SQLStatement   = $PDOObject->prepare("SELECT customerName, contactFirstName, contactLastName, phone FROM customers WHERE customerNumber = :CustomerNumber");
            # Execure the query with the provided parameter value 
            $QueryRes       = $SQLStatement->execute(['CustomerNumber' => $CustomerNumber]);

            # If the query was successful...
            if ($QueryRes === true)
            {
              # ...then get the record from the query
              $Customer = $SQLStatement->fetch();
              # If we got the value...
              if ($Customer)
              {
                # ...then convert the returned object to JSON format and return a 200 - OK error code
                $Response = $Response->withJson($Customer, 200);
              } else
              {
                # ...else return the error information in JSON format
                $Response = $Response->withJson("ERROR: No customer with number '" . $CustomerNumber . "' could be found!", 204);
              }
            } else
            {
              # ...else return the error information in JSON format
              $Response->write(json_encode("ERROR: Select returned error '" . $SQLStatement->errorCode() . "'!"));
              $Response = $Response->withStatus(500);
            }
            
            # Return the response
            return $Response;
          }
        );
        
        $this->post('/', function(Request $Request, Response $Response, $Args)
          {
            # Log to file which REST endpoint was reached and which method was used
            $this->logger->debug("Endpoint /Customer reached with method POST");

            # Prepare to access the container
            global $container;
            # Get the environment variables
            $environment = $container['environment'];

            # Log the authentication information to file
            $this->logger->debug("User: " . $environment["PHP_AUTH_USER"] . " - Password: ********");

            # If authentication information was provided...
            if (($environment["PHP_AUTH_USER"] != "") and ($environment["PHP_AUTH_PW"] != ""))
            {
              # ...then open a connection to the LDAP directory service  
              $LDAPConn = $this->get('ldap');

              # If the connection was successful...    
			  if ($LDAPConn)
			  {
                # ...then prepare to bind to the LDAP directory service
			    $LDAPBind = false;

                # Prepare to get the LDAP configuration information
				global $config;

                # If TLS should be used...
			    if ($config['ldap']['usetls'] == true)
			    {
                  # ...then if StartTLS was successful...
				  if (ldap_start_tls($LDAPConn))
				  {
                    # ...then bind to the LDAP directory service using the credentials in the Basic authentication
				    $LDAPBind = @ldap_bind($LDAPConn, $environment["PHP_AUTH_USER"], $environment["PHP_AUTH_PW"]);
				  } else
                  {
                    # ...else convert the error information to JSON format and return a 401 - Unauthorized error code
				    $LDAPErr = ldap_error($LDAPConn);
				    ldap_get_option($LDAPConn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $LDAPOpt);
                    $Response = $Response->withJson("ERROR: Start TLS failed (" . $LDAPErr . ", " . $LDAPOpt . ")!", 412);
                  }
                } else
			    {
                  # ...else bind to the LDAP directory service using the credentials in the Basic authentication
				  $LDAPBind = @ldap_bind($LDAPConn, $environment["PHP_AUTH_USER"], $environment["PHP_AUTH_PW"]);
			    }

                # If the LDAP bind was successful...
			    if ($LDAPBind)
			    {
                  # ...then get the form data from the POST request
                  $FormData = $Request->getParsedBody();
            
                  # Get the sanitized parameters from the form
                  $CustomerNumber         = filter_var($FormData['customerNumber'], FILTER_SANITIZE_STRING);
                  $CustomerName           = filter_var($FormData['customerName'], FILTER_SANITIZE_STRING);
                  $ContactLastName        = filter_var($FormData['contactLastName'], FILTER_SANITIZE_STRING);
                  $ContactFirstName       = filter_var($FormData['contactFirstName'], FILTER_SANITIZE_STRING);
                  $Phone                  = filter_var($FormData['phone'], FILTER_SANITIZE_STRING);
                  $AddressLine1           = filter_var($FormData['addressLine1'], FILTER_SANITIZE_STRING);
                  $AddressLine2           = filter_var($FormData['addressLine2'], FILTER_SANITIZE_STRING);
                  $City                   = filter_var($FormData['city'], FILTER_SANITIZE_STRING);
                  $State                  = filter_var($FormData['state'], FILTER_SANITIZE_STRING);
                  $PostalCode             = filter_var($FormData['postalCode'], FILTER_SANITIZE_STRING);
                  $Country                = filter_var($FormData['country'], FILTER_SANITIZE_STRING);
                  $SalesRepEmployeeNumber = filter_var($FormData['salesRepEmployeeNumber'], FILTER_SANITIZE_STRING);
                  $CreditLimit            = filter_var($FormData['creditLimit'], FILTER_SANITIZE_STRING);

                  # Log to file the parameters used:
                  $this->logger->debug("Endpoint /Customer parameters:");
                  $this->logger->debug("CustomerNumber: $CustomerNumber - CustomerName: $CustomerName");
                  $this->logger->debug("ContactLastName: $ContactLastName - ContactFirstName: $ContactFirstName");
                  $this->logger->debug("Phone: $Phone");
                  $this->logger->debug("AddressLine1: $AddressLine1 - AddressLine2: $AddressLine2 - City: $City - State: $State - PostalCode: $PostalCode - Country: $Country");
                  $this->logger->debug("SalesRepEmployeeNumber: $SalesRepEmployeeNumber - CreditLimit: $CreditLimit");
            
                  # If all of the required parameters are present...            
                  if ((!is_null($CustomerNumber)) && (!is_null($CustomerName)) && (!is_null($ContactLastName)) && (!is_null($ContactFirstName)) &&
                      (!is_null($Phone)) && (!is_null($AddressLine1))&& (!is_null($City)) && (!is_null($Country)))
                  {
                    # ...then prepare a hashtable with the database column data
                    $Data = [
                      'CustomerNumber' => $CustomerNumber,
                      'CustomerName' => $CustomerName,
                      'ContactLastName' => $ContactLastName,
                      'ContactFirstName' => $ContactFirstName,
                      'Phone' => $Phone,
                      'AddressLine1' => $AddressLine1,
                      'AddressLine2' => $AddressLine2,
                      'City' => $City,
                      'State' => $State,
                      'PostalCode' => $PostalCode,
                      'Country' => $Country,
                      'SalesRepEmployeeNumber' => $SalesRepEmployeeNumber,
                      'CreditLimit' => $CreditLimit,
                    ];

                    # Get the database connection object 
                    $PDOObject      = $this->get('db');

                    # Prepare a SQL query to execute
                    $SQLStatement = $PDOObject->prepare("INSERT INTO customers (customerNumber, customerName, contactLastName, contactFirstName, phone, addressLine1, addressLine2, city, state, postalCode, country, salesRepEmployeeNumber, creditLimit) VALUES (:CustomerNumber, :CustomerName, :ContactLastName, :ContactFirstName, :Phone, :AddressLine1, :AddressLine2, :City, :State, :PostalCode, :Country, :SalesRepEmployeeNumber, :CreditLimit)");
                    # Execure the query with the data from the form data as parameters
                    $Result       = $SQLStatement->execute($Data);
          
                    # If the query was successful...
                    if ($Result === true)
                    {
                      # ...then return HTTP result code 201 - Created
                      $Response = $Response->withStatus(201);
                    } else
                    {
                      # ...else convert the error message from the operation to JSON format and return a 400 - Bad request error code
                      $Response = $Response->withJson("ERROR: Insert returned error '" . $SQLStatement->errorCode() . "'!", 400);
                    }
                  } else
                  {
                    # ...else convert the error message from the operation to JSON format and return a 400 - Bad request error code
                    $Response = $Response->withJson("Mandatory paramter missing!", 400);
                  }
    		    } else
                {
                  # ...else convert the error information to JSON format and return a 401 - Unauthorized error code
				  $LDAPErr   = ldap_error($LDAPConn);
				  ldap_get_option($LDAPConn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $LDAPOpt);
                  $ErrorsMsg = "ERROR: LDAP bind failed (" . $LDAPErr . ", " . $LDAPOpt . ")!";
                  $Response  = $Response->withJson($ErrorsMsg, 401);
                  $this->logger->debug($ErrorsMsg);
                }
			  } else
              {
                # ...else convert the error information to JSON format and return a 401 - Unauthorized error code
			    $LDAPErr = ldap_error($LDAPConn);
                ldap_get_option($LDAPConn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $LDAPOpt);
                $ErrorsMsg = "ERROR: LDAP connect failed (" . $LDAPErr . ", " . $LDAPOpt . ")!";
                $Response  = $Response->withJson($ErrorsMsg, 401);
                $this->logger->debug($ErrorsMsg);
              }
            } else
            {
              # ...else convert the error information to JSON format and return a 401 - Unauthorized error code
              $ErrorsMsg = "ERROR: Authorization is required to submit data!";
              $Response  = $Response->withJson($ErrorsMsg, 401);
              $this->logger->debug($ErrorsMsg);
            }

            # Return the response
            return $Response;
          }
        );
      });
  }
);
            
# Run the application 
$app->run();

?>