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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ProductController extends Controller
{

    /**
     * Adds a new product to the database.
     * @Route("/products", name="products_add")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse ID of the new product
     * @throws \Exception
     */
    public function addAction(Request $request) {
        $data = json_decode($request->getContent(), true);

        $product = new Product();
        $product->setTitle($data["title"]);
        $product->setPrice($data["price"]);
        $product->setDateAdded(new \DateTime());

        try {
            $em = $this->getDoctrine()->getManager();
            $em->persist($product);
            $em->flush();
            $response = new JsonResponse(array(
                "id" => $product->getId()
            ));
            $response->setStatusCode(Response::HTTP_CREATED);
            $response->headers->set("Location", $this->generateUrl('product_show', array('productId'=>$product->getId()), UrlGeneratorInterface::ABSOLUTE_URL));
            return $response;
        } catch (\Exception $e) {
            throw new \Exception("Problem with the database");
        }
    }

    /**
     * Removes product from the database.
     * Doesn't allow to remove Product from the database as long as the Product is in any of the Carts.
     * @Route("/products/{productId}", name="products_remove")
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
     * Returns product information from the database.
     * @Route("/products/{productId}", name="products_show")
     * @Method("GET")
     * @param int $productId
     * @return JsonResponse Product info
     * @throws \Exception
     */
    public function showAction($productId) {
        /** @var Product $product */
        $product = $this->getDoctrine()->getRepository("AppBundle:Product")->findOneById($productId);
        if ( is_null($product) ) {
            throw $this->createNotFoundException();
        } else {
            return new JsonResponse($product,200);
        }
    }

    /**
     * @Route("/products", name="products_list" )
     * @Method("GET")
     * @return JsonResponse Paginated list of products
     */
    public function listAction(Request $request) {
        $productsPerPage = $this->getParameter("products_per_page");

        $page = $request->query->get("page");
        if ( is_null($page) ) $page = [ "number"=>1, "size"=>$productsPerPage ];
        if ( !is_array($page) ) $page = [ "number"=>intval($page), "size"=>$productsPerPage ];

        

        return new JsonResponse($page);
    }

    /**
     * @Route("/products/{productId}", name="product_update")
     * @Method("PUT")
     * @param Request $request
     */
    public function updateAction(Request $request) {

    }

}