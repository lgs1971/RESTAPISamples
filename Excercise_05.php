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
  return $pdo;
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
        # Add a new endpoint under the v1/Customer group with a parameter named CustomerNumber, accessible using the GET methoc
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

            # Get the form data from the POST request
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
              $Response = $Response->withJson("Mandatory parameter missing!", 406);
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