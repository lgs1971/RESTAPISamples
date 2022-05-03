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
      });
  }
);
            
# Run the application 
$app->run();

?>