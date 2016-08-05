<?php
/**
 * Created by PhpStorm.
 * User: piotr.grzegorzewski
 * Date: 05/08/2016
 * Time: 13:58
 */

namespace AppBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use AppBundle\Entity\Product;

class LoadProductsData extends AbstractFixture implements OrderedFixtureInterface
{

    /**
     * Load data fixtures with the passed EntityManager
     *
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        $products = array(
            array("title"=>"Fallout", "price"=>1.99),
            array("title"=>"Don’t Starve", "price"=>2.99),
            array("title"=>"Baldur’s Gate", "price"=>3.99),
            array("title"=>"Icewind Dale", "price"=>4.99),
            array("title"=>"Bloodborne", "price"=>5.99)
        );
        foreach ( $products as $product ) {
            $p = new Product();
            $p->setTitle($product["title"]);
            $p->setPrice($product["price"]);
            $p->setDateAdded(new \DateTime("now"));
            $manager->persist($p);
        }
        $manager->flush();
    }

    /**
     * Get the order of this fixture
     *
     * @return integer
     */
    public function getOrder()
    {
        return 1;
    }
}