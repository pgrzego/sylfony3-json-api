<?php
/**
 * Created by PhpStorm.
 * User: piotr.grzegorzewski
 * Date: 26/07/2016
 * Time: 00:30
 */

namespace AppBundle\Controller;


use AppBundle\Entity\Product;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    /**
     * Sets up initial rows in the database
     * @Route("/init", name="init_setup")
     */
    public function initAction() {
        $em = $this->getDoctrine()->getManager();

        $products = $em->getRepository("AppBundle:Product")
            ->findAll();

        if ( empty($products) ) {
            $initProducts = array(
                array(
                    "title" => "Fallout",
                    "price" => 1.99
                ),
                array(
                    "title" => "Don’t Starve",
                    "price" => 2.99
                ),
                array(
                    "title" => "Baldur’s Gate",
                    "price" => 3.99
                ),
                array(
                    "title" => "Icewind Dale",
                    "price" => 4.99
                ),
                array(
                    "title" => "Bloodborne",
                    "price" => 5.99
                )
            );
            foreach ( $initProducts as $product ) {
                $p = new Product();
                $p->setPrice($product["price"]);
                $p->setTitle($product["title"]);
                $p->setDateAdded(new \DateTime());
                $em->persist($p);
            }
            $em->flush();
            $response = "The initial configuration has been set up.";
        } else {
            $response = "The products are already in the database";
        }

        return new Response($response);

    }
}