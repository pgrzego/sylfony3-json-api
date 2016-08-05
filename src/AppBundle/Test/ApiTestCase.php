<?php
/**
 * Created by PhpStorm.
 * User: piotr.grzegorzewski
 * Date: 05/08/2016
 * Time: 15:36
 */

namespace AppBundle\Test;


use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\Console\Output\ConsoleOutput;

class ApiTestCase extends WebTestCase
{

    /** @var Client */
    protected $client;
    /** @var  ConsoleOutput */
    private $output;

    public function setUp()
    {
        $this->client = $this->createClient();
        $loader = new Loader();
        $loader->loadFromDirectory('src/AppBundle/DataFixtures/ORM');
        $purger = new ORMPurger();

        $executor = new ORMExecutor($this->client->getContainer()->get('doctrine')->getManager(), $purger);
        $executor->execute($loader->getFixtures());
        parent::setUp();
    }


    /**
     * @param \Exception|\Throwable $e
     */
    protected function onNotSuccessfulTest($e)
    {
        $this->printDebug('');
        $this->printDebug('<error>Failure!</error> when making the following request: '.$this->client->getRequest()->getPathInfo());
        //$this->printDebug($this->client->getResponse()->getContent());
        $this->printDebug('');
        parent::onNotSuccessfulTest($e);
    }

    protected function printDebug($string)
    {
        if ($this->output === null) {
            $this->output = new ConsoleOutput();
        }
        $this->output->writeln($string);
    }

}