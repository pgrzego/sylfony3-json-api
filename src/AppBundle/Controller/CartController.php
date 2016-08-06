<?php
/**
 * Created by PhpStorm.
 * User: piotr.grzegorzewski
 * Date: 25/07/2016
 * Time: 17:21
 */

namespace AppBundle\Controller;


use AppBundle\Entity\Cart;
use AppBundle\Entity\CartProduct;
use AppBundle\Entity\Product;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CartController extends BaseController
{

    /**
     * @Route("/carts/{$cartId}", name="carts_show")
     * @Method("GET")
     * @param $cartId
     * @return Response
     */
    public function showAction($cartId) {
        $cart = $this->getDoctrine()->getRepository("AppBundle:Cart")->findById($cartId);
        if ( is_null($cart) ) return $this->createNotFoundResponse("Cart", $cartId);

        return $this->createApiResponse($cart, Response::HTTP_OK);
    }


    /**
     * Adds a new, empty Cart
     * @Route("/carts", name="carts_create")
     * @Method("POST")
     * @return Response new Cart object info
     * @throws \Exception
     */
    public function createAction() {
        $cart = new Cart();
        $cart->setDateCreated( new \DateTime("now") );
        $cart->setTotalPrice(0);

        try {
            $em = $this->getDoctrine()->getManager();
            $em->persist($cart);
            $em->flush();
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database.");
        }

        $response = $this->createApiResponse($cart,Response::HTTP_CREATED);
        $response->headers->set("Location", $this->generateUrl('carts_show', array('cartId'=>$cart->getId()), UrlGeneratorInterface::ABSOLUTE_URL));
        return $response;
    }

    /**
     * Removes the Cart from database. Doesn't check if there were products inside.
     * @Route("/carts/{cartId}", name="carts_remove")
     * @Method("DELETE")
     * @param int $cartId
     * @return Response
     * @throws \Exception
     */
    public function removeAction($cartId) {
        // TODO: Consider if there should be a check here if the cart is empty
        $cartId = intval($cartId);

        $em = $this->getDoctrine()->getManager();
        try {
            $cart = $em->getRepository("AppBundle:Cart")
                ->find($cartId);
            if ( is_null($cart) )
                return $this->createNotFoundResponse("Cart", $cartId);

            $em->remove($cart);
            $em->flush();
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database");
        }

        return $this->createApiResponse(null,Response::HTTP_NO_CONTENT);
    }

    /**
     * Increments quantity of Product in the Cart by 1.
     * Calculates new total price of the Cart.
     * Both the Cart and the Product must exist. Otherwise an error is raised.
     * @Route("/carts/{cartId}/{productId}", name="carts_add_product")
     * @Method("POST")
     * @param int $cartId
     * @param int $productId
     * @return Response
     * @throws \Exception
     */
    public function addProductAction($cartId, $productId) {
        $cartId = intval($cartId);
        $productId = intval($productId);
        $em = $this->getDoctrine()->getManager();
        try {
            $cart = $em->getRepository("AppBundle:Cart")
                ->find($cartId);
            if ( is_null($cart) )
                return $this->createNotFoundResponse("Cart", $cartId);

            $product = $em->getRepository("AppBundle:Product")
            ->find($productId);

            if ( is_null($product) )
                return $this->createNotFoundResponse("Product", $productId);

            $cartProduct = $em->getRepository("AppBundle:CartProduct")
                ->findOneBy(array(
                    "product" => $productId,
                    "cart" => $cartId
                ));
            if (is_null($cartProduct)) {
                $cartProduct = new CartProduct();
                $cartProduct->setProduct($product);
                $cartProduct->setCart($cart);
                $cartProduct->setQuantity(1);
                $em->persist($cartProduct);
            } else {
                $cartProduct->setQuantity( $cartProduct->getQuantity()+1 );
            }

            $cart->setTotalPrice( $cart->getTotalPrice()+$product->getPrice() );

            $em->flush();

            // TODO: change this so it returns a selected product in the cart
            return $this->createApiResponse($cart,Response::HTTP_OK);
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database.");
        }
    }


    /**
     * Decrements quantity of Product in the Cart by 1.
     * If quantity becomes 0 then the Product is removed from the Cart.
     * Calculates new total price of the Cart.
     * @Route("/carts/{cartId}/{productId}", name="carts_remove_product")
     * @Method("DELETE")
     * @param int $cartId
     * @param int $productId
     * @return Response
     * @throws \Exception
     */
    public function removeProductAction($cartId, $productId) {
        $cartId = intval($cartId);
        $productId = intval($productId);
        $em = $this->getDoctrine()->getManager();
        try {
            $cart = $em->getRepository("AppBundle:Cart")
                ->find($cartId);
            if ( is_null($cart) )
                return $this->createNotFoundResponse("Cart", $cartId);

            $product = $em->getRepository("AppBundle:Product")
                ->find($productId);

            if ( is_null($product) )
                return $this->createNotFoundResponse("Product", $productId);

            $cartProduct = $em->getRepository("AppBundle:CartProduct")
                ->findOneBy(array(
                    "product" => $productId,
                    "cart" => $cartId
                ));
            if (is_null($cartProduct))
                return $this->createNotFoundResponse("Product", $productId, "Product $productId not found in this cart $cartId");

            $quantity = $cartProduct->getQuantity()-1;
            if ( $quantity <= 0 )
                $em->remove($cartProduct);
            else
                $cartProduct->setQuantity( $quantity );

            $cart->setTotalPrice( $cart->getTotalPrice()-$product->getPrice() );

            $em->flush();

            // TODO: change this so it returns a selected product in the cart
            return $this->createApiResponse($cart,Response::HTTP_OK);
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database: ".$e->getMessage());
        }
    }

    /**
     * Sets quantity of Product in the Cart.
     * Calculates new total price of the Cart.
     * If quantity is equal or less than 0 then the Product is removed from the Cart.
     * @Route("/carts/{cartId}/{productId}/{quantity}", name="carts_update_product")
     * @Method("PUT")
     * @param int $cartId
     * @param int $productId
     * @param int $quantity
     * @return Response
     * @throws \Exception
     */
    public function setProductAction($cartId, $productId, $quantity) {
        $cartId = intval($cartId);
        $productId = intval($productId);
        $quantity = intval($quantity);
        $deltaQuantity = 0;
        $em = $this->getDoctrine()->getManager();
        try {
            $cart = $em->getRepository("AppBundle:Cart")
                ->find($cartId);
            if ( is_null($cart) )
                return $this->createNotFoundResponse("Cart", $cartId);

            $product = $em->getRepository("AppBundle:Product")
                ->find($productId);
            if ( is_null($product) )
                return $this->createNotFoundResponse("Product", $productId);


            $cartProduct = $em->getRepository("AppBundle:CartProduct")
                ->findOneBy(array(
                    "product" => $productId,
                    "cart" => $cartId
                ));
            if (is_null($cartProduct) ) {
                if ( $quantity ) {
                    $cartProduct = new CartProduct();
                    $cartProduct->setProduct($product);
                    $cartProduct->setCart($cart);
                    $cartProduct->setQuantity($quantity);
                    $errors = $this->get('validator')->validate($cartProduct);
                    if ( count($errors) ) {
                        return $this->createValidationErrorResponse($errors);
                    }
                    $em->persist($cartProduct);
                    $deltaQuantity = $quantity;
                }
            } else {
                if ( $quantity<=0 ) {
                    $em->remove($cartProduct);
                    $deltaQuantity = 0-$cartProduct->getQuantity();
                } else {
                    $deltaQuantity = $quantity - $cartProduct->getQuantity();
                    $cartProduct->setQuantity( $quantity );
                    $errors = $this->get('validator')->validate($cartProduct);
                    if ( count($errors) ) {
                        return $this->createValidationErrorResponse($errors);
                    }
                }
            }

            $cart->setTotalPrice( $cart->getTotalPrice()+$deltaQuantity*$product->getPrice() );

            $em->flush();

            // TODO: change this so it returns a selected product in the cart
            return $this->createApiResponse($cart,Response::HTTP_OK);
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database: ".$e->getMessage());
        }
    }

    /**
     * @Route("/carts/{cartId}/products", name="carts_show")
     * @Method("GET")
     * @param int $cartId
     * @return Response List of products with quantities.
     * @throws \Exception
     */
    public function listProductsAction($cartId) {
        $cartId = intval($cartId);
        $em = $this->getDoctrine()->getManager();
        $cart = $em->getRepository("AppBundle:Cart")
            ->find($cartId);
        if ( is_null($cart) )
            throw $this->createNotFoundException("Cart not found");

        /** @var CartProduct[] $cartProducts */
        $cartProducts = $em->getRepository("AppBundle:CartProduct")
            ->findByCart($cartId);

        $response = array();
        foreach ( $cartProducts as $cartProduct ) {
            $product = $cartProduct->getProduct();
            $response[] = array(
                "id" => $product->getId(),
                "title" => $product->getTitle(),
                "price" => $product->getPrice(),
                "quantity" => $cartProduct->getQuantity()
            );
        }

        return new JsonResponse($response);
    }

    /**
     * @Route("/carts/test", name="carts_test")
     * @Method("GET")
     * @return Response List of products with quantities.
     * @throws \Exception
     */
    public function testAction() {
        return new Response(__DIR__.DIRECTORY_SEPARATOR.'json-api-schema.json');
        //return new JsonResponse( $this->getParameter("products_do_not_remove") );
    }

    /**
     * @param Cart $cart
     * @return string
     */
    private function cartJsonResponse($cart, $resourceObject = true) {
        return json_encode([
           "data" => [
               "id" => $cart->getId(),
               "totalPrice" => $cart->getTotalPrice(),
               "created" => $cart->getDateCreated()
           ]
        ]);
    }

}