<?php
/**
 * Created by PhpStorm.
 * User: piotr.grzegorzewski
 * Date: 26/07/2016
 * Time: 01:25
 */

namespace AppBundle\Controller;


use AppBundle\Entity\Product;
use Doctrine\DBAL\DBALException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductController extends Controller
{

    /**
     * Adds a new product to the database.
     * @Route("/product", name="product_add")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse ID of the new product
     * @throws \Exception
     */
    public function addAction(Request $request) {
        $title = $request->request->get("title");
        $price = $request->request->get("price");
        // TODO: check if this data is sanitized by Symfony
        if ( $title && $price>0 ) {
            $product = new Product();
            $product->setTitle($title);
            $product->setPrice($price);
            $product->setDateAdded(new \DateTime());
            try {
                $em = $this->getDoctrine()->getManager();
                $em->persist($product);
                $em->flush();
                return new JsonResponse(array(
                   "id" => $product->getId()
                ));
            } catch (\Exception $e) {
                throw new \Exception("Problem with the database");
            }
        } else {
            throw new \Exception("Missing or bad parameters. Expected `title` as a string and `price` as a positive float.");
        }
    }

    /**
     * Removes product from the database.
     * Doesn't allow to remove Product from the database as long as the Product is in any of the Carts.
     * @Route("/product/{productId}", name="product_remove")
     * @Method("DELETE")
     * @param int $productId
     * @return JsonResponse ID of removed Product
     * @throws \Exception
     */
    public function removeAction($productId) {
        $productId = intval($productId);
        $em = $this->getDoctrine()->getManager();
        try {
            $product = $em->getRepository("AppBundle:Product")
                ->find($productId);
            if ( is_null($product) )
                throw $this->createNotFoundException();

            // TODO: if it shouldn't check if product is inside cart then total price for every cart needs to be recalculated.
            $cartProducts = $em->getRepository("AppBundle:CartProduct")
                ->findByProduct($productId);
            if ( !empty($cartProducts) ) {
                throw new \Exception("Product exists in Carts. Can't remove it.");
            }

            $em->remove($product);
            $em->flush();
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ( $e instanceof DBALException ) {
                throw new \Exception("There was a problem with the database.");
            } else {
                throw $e;
            }
        }

        return new JsonResponse(array(
            "id"=>$productId
        ));
    }

    /**
     * @Route("/product/list/{page}", name="product_list", defaults={"page"=1} )
     * @Method("GET")
     * @param int $page
     * @return JsonResponse Paginated list of products
     */
    public function listAction($page) {
        $productsPerPage = $this->getParameter("products_per_page");
        // TODO: add bundle to paginate
    }

    /**
     * @Route("/product/{productId}", name="product_update")
     * @Method("PUT")
     * @param Request $request
     */
    public function updateAction(Request $request) {

    }

}