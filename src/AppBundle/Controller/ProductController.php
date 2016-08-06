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
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ProductController extends BaseController
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

        $errors = $this->get('validator')->validate($product);
        if ( count($errors) ) {
            // TODO: Handle errors according to example http://jsonapi.org/examples/#error-objects-error-codes
            $data = array(
                'type' => 'validation_error',
                'title' => 'There was a validation error',
                'errors' => $errors
            );
            $response = new JsonResponse($data, Response::HTTP_UNPROCESSABLE_ENTITY);
            $response->headers->set('Content-Type', 'application/vnd.api+json');
            return $response;
        }

        try {
            $em = $this->getDoctrine()->getManager();
            $em->persist($product);
            $em->flush();
            $response = new JsonResponse($product);
            $response->setStatusCode(Response::HTTP_CREATED);
            $response->headers->set("Location", $this->generateUrl('products_show', array('productId'=>$product->getId()), UrlGeneratorInterface::ABSOLUTE_URL));
            $response->headers->set('Content-Type', 'application/vnd.api+json');
            return $response;
        } catch (\Exception $e) {
            throw $e;//new \Exception("Problem with the database");
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

        if ( in_array($productId, $this->getParameter("products_do_not_remove")) ) {
            return $this->createErrorResponse("Invalid Product", "This product can't be removed", 403);
        }

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

        $response = new JsonResponse(null, Response::HTTP_NO_CONTENT);
        $response->headers->set('Content-Type', 'application/vnd.api+json');
        return $response;
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
        $page = $request->query->get("page", 1);

        $qb = $this->getDoctrine()
            ->getRepository('AppBundle:Product')
            ->findAllQueryBuilder();

        $adapter = new DoctrineORMAdapter($qb);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage($productsPerPage);
        $pagerfanta->setCurrentPage($page);

        $products = [];
        foreach ($pagerfanta->getCurrentPageResults() as $result) {
            $products[] = $result;
        }

        return new JsonResponse($page, Response::HTTP_OK);
    }

    /**
     * @Route("/products/{productId}", name="product_update")
     * @Method("PUT")
     * @param int $productId
     * @param Request $request
     * @return JsonResponse
     */
    public function updateAction($productId, Request $request) {

        $em = $this->getDoctrine()->getManager();
        /** @var Product $product */
        $product = $em->getRepository("AppBundle:Product")
            ->find($productId);

        if ( is_null($product) )
            throw $this->createNotFoundException();

        $data = json_decode( $request->getContent(), true );

        $product->setTitle($data["title"]);
        $product->setPrice($data["price"]);

        $errors = $this->get('validator')->validate($product);
        if ( count($errors) ) {
            // TODO: Handle errors according to example http://jsonapi.org/examples/#error-objects-error-codes
            $data = array(
                'type' => 'validation_error',
                'title' => 'There was a validation error',
                'errors' => $errors
            );
            $response = new JsonResponse($data, Response::HTTP_UNPROCESSABLE_ENTITY);
            $response->headers->set('Content-Type', 'application/vnd.api+json');
            return $response;
        }

        $em->flush();

        return new JsonResponse($product, Response::HTTP_OK);
    }

}