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

# Create the application instance and configure it
$app = new \Slim\App(['settings' => $config]);

# Define a new endpoint and the response for a GET request
$app->get('/', function (Request $Request, Response $Reponse)
  {
    # Return a message for anyone that reaches this REST endpoint
    $Reponse->getBody()->write("This is the REST API that allows REST access to a database server");
    return $Reponse;
  }
);
            
# Run the application 
$app->run();

?>