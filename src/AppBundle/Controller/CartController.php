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
use Doctrine\DBAL\Driver\PDOException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CartController extends Controller
{
    /**
     * Adds a new, empty Cart
     * @Route("/cart", name="cart_create")
     * @Method("POST")
     * @return JsonResponse ID of the new Cart
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


        return new JsonResponse(array(
            "id"=>$cart->getId()
        ));
    }

    /**
     * Removes the Cart from database. Doesn't check if there were products inside.
     * @Route("/cart/{cartId}", name="cart_remove")
     * @Method("DELETE")
     * @param int $cartId
     * @return JsonResponse ID of the removed Cart
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
                throw $this->createNotFoundException();

            $em->remove($cart);
            $em->flush();
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database");
        }

        return new JsonResponse(array(
            "id"=>$cartId
        ));
    }

    /**
     * Increments quantity of Product in the Cart by 1.
     * Calculates new total price of the Cart.
     * Both the Cart and the Product must exist. Otherwise an error is raised.
     * @Route("/cart/{cartId}/{productId}", name="cart_add_product")
     * @Method("POST")
     * @param int $cartId
     * @param int $productId
     * @return JsonResponse New quantity of product and total Cart price.
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
                throw $this->createNotFoundException("Cart not found");

            $product = $em->getRepository("AppBundle:Product")
            ->find($productId);

            if ( is_null($product) )
                throw $this->createNotFoundException("Product not found");

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

            return new JsonResponse(array(
                "quantity"=>$cartProduct->getQuantity(),
                "totalPrice"=>$cart->getTotalPrice()
            ));
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database.");
        }
    }


    /**
     * Decrements quantity of Product in the Cart by 1.
     * If quantity becomes 0 then the Product is removed from the Cart.
     * Calculates new total price of the Cart.
     * @Route("/cart/{cartId}/{productId}", name="cart_remove_product")
     * @Method("DELETE")
     * @param int $cartId
     * @param int $productId
     * @return JsonResponse New quantity of product and total Cart price.
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
                throw $this->createNotFoundException("Cart not found");

            $product = $em->getRepository("AppBundle:Product")
                ->find($productId);

            if ( is_null($product) )
                throw $this->createNotFoundException("Product not found");

            $cartProduct = $em->getRepository("AppBundle:CartProduct")
                ->findOneBy(array(
                    "product" => $productId,
                    "cart" => $cartId
                ));
            if (is_null($cartProduct))
                throw $this->createNotFoundException("Product not found in this cart");

            $quantity = $cartProduct->getQuantity()-1;
            if ( $quantity <= 0 )
                $em->remove($cartProduct);
            else
                $cartProduct->setQuantity( $quantity );

            $cart->setTotalPrice( $cart->getTotalPrice()-$product->getPrice() );

            $em->flush();

            return new JsonResponse(array(
                "quantity"=>$quantity,
                "totalPrice"=>$cart->getTotalPrice()
            ));
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database: ".$e->getMessage());
        }
    }

    /**
     * Sets quantity of Product in the Cart.
     * Calculates new total price of the Cart.
     * If quantity is equal or less than 0 then the Product is removed from the Cart.
     * @Route("/cart/{cartId}/{productId}/{quantity}", name="cart_update_product")
     * @Method("PUT")
     * @param int $cartId
     * @param int $productId
     * @param int $quantity
     * @return JsonResponse New quantity of product and total Cart price.
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
                throw $this->createNotFoundException("Cart not found");

            $product = $em->getRepository("AppBundle:Product")
                ->find($productId);
            if ( is_null($product) )
                throw $this->createNotFoundException("Product not found");


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
                        $errorsString = (string) $errors;
                        return new Response($errorsString);
                    }
                    //$em->persist($cartProduct);
                    //$deltaQuantity = $quantity;
                }
            } else {
                if ( $quantity<=0 ) {
                    $em->remove($cartProduct);
                    $deltaQuantity = 0-$cartProduct->getQuantity();
                } else {
                    $deltaQuantity = $quantity - $cartProduct->getQuantity();
                    $cartProduct->setQuantity( $quantity );
                    $validator = $this->get('validator');
                    $errors = $validator->validate($cartProduct);
                    if ( count($errors) ) {
                        $errorsString = (string) $errors;
                        throw new HttpException(409, $errorsString);
                    }
                }
            }

            $cart->setTotalPrice( $cart->getTotalPrice()+$deltaQuantity*$product->getPrice() );

            $em->flush();

            return new JsonResponse(array(
                "quantity"=>$cartProduct->getQuantity(),
                "totalPrice"=>$cart->getTotalPrice()
            ));
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database: ".$e->getMessage());
        }
    }

    /**
     * @Route("/cart/{cartId}", name="cart_list_products")
     * @Method("GET")
     * @param int $cartId
     * @return JsonResponse List of products with quantities.
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

}