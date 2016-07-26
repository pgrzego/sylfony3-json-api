<?php
/**
 * Created by PhpStorm.
 * User: piotr.grzegorzewski
 * Date: 26/07/2016
 * Time: 15:56
 */

namespace AppBundle\EventListener;


use Doctrine\DBAL\Driver\PDOException;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

class DBExceptionListener
{
    public function onPdoException(GetResponseForExceptionEvent $event) {
        $exception = $event->getException();
        if ( $exception instanceof PDOException) {
            throw new \Exception("There was a problem with the database.");
        }
    }

}