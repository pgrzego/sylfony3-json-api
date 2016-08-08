<?php
/**
 * Created by PhpStorm.
 * User: piotr.grzegorzewski
 * Date: 05/08/2016
 * Time: 15:36
 */

namespace AppBundle\Test;

use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\Console\Output\ConsoleOutput;

class ApiTestCase extends WebTestCase
{

    /** @var Client */
    protected $client;
    /** @var  ConsoleOutput */
    protected $output;
    /** @var EntityManager */
    protected $em;

    public function setUp()
    {
        $kernel = static::createKernel();
        $kernel->boot();
        $this->em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
        $this->em->beginTransaction();

        $this->client = $this->createClient();

        parent::setUp();
    }

    public function tearDown()
    {
        $this->em->rollback();
    }

    /**
     * @param \Exception|\Throwable $e
     */
    protected function onNotSuccessfulTest($e)
    {
        $this->printDebug('');
        $this->printDebug('<error>Failure!</error> when making the following request: '.$this->client->getRequest()->getMethod().": ".$this->client->getRequest()->getPathInfo());

        $content = $this->client->getResponse()->getContent();
        $start = strpos($content, "<title>");
        $start = ( $start === false )?0:$start+7;
        if ( $start ) $content = htmlspecialchars_decode( trim(substr($content,$start,strpos($content,"</title>")-$start)) );
        $this->printDebug($content);
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