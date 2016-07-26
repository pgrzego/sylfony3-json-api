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
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
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

        if ( !is_integer($cartId) )
            throw new \Exception("Wrong type of parameter: $cartId. Should be integer.");
        $em = $this->getDoctrine()->getManager();
        try {
            $cart = $em->getRepository("AppBundle:Cart")
                ->find($cartId);
            if ( is_null($cart) )
                throw $this->createNotFoundException();

            $em->remove($cart);
            $em->flush();
        } catch (HttpException $e) {
            throw new HttpException($e->getMessage());
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
        if ( !is_integer($cartId) )
            throw new \Exception("Wrong type of parameter: $cartId. Should be integer.");
        if ( !is_integer($productId) )
            throw new \Exception("Wrong type of parameter: $productId. Should be integer.");
        $em = $this->getDoctrine()->getManager();
        try {
            $cart = $em->getRepository("AppBundle:Cart")
                ->find($cartId);
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database");
        }

        if ( is_null($cart) )
            throw $this->createNotFoundException("Cart not found");
        try {
            $product = $em->getRepository("AppBundle:Product")
            ->find($productId);
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database");
        }
        if ( is_null($product) )
            throw $this->createNotFoundException("Product not found");


        try {
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
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database: ".$e->getMessage());
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
        if ( !is_integer($cartId) )
            throw new \Exception("Wrong type of parameter: $cartId. Should be integer.");
        if ( !is_integer($productId) )
            throw new \Exception("Wrong type of parameter: $productId. Should be integer.");
        $em = $this->getDoctrine()->getManager();
        try {
            $cart = $em->getRepository("AppBundle:Cart")
                ->find($cartId);
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database");
        }

        if ( is_null($cart) )
            throw $this->createNotFoundException("Cart not found");
        try {
            $product = $em->getRepository("AppBundle:Product")
                ->find($productId);
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database");
        }
        if ( is_null($product) )
            throw $this->createNotFoundException("Product not found");


        try {
            $cartProduct = $em->getRepository("AppBundle:CartProduct")
                ->findOneBy(array(
                    "product" => $productId,
                    "cart" => $cartId
                ));
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database");
        }
        if (is_null($cartProduct))
            throw $this->createNotFoundException("Product not found in this cart");

        try {
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
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database");
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
        if ( !is_integer($cartId) )
            throw new \Exception("Wrong type of parameter: $cartId. Should be integer.");
        if ( !is_integer($productId) )
            throw new \Exception("Wrong type of parameter: $productId. Should be integer.");
        if ( !is_integer($quantity) )
            throw new \Exception("Wrong type of parameter: $quantity. Should be integer.");
        $deltaQuantity = 0;
        $em = $this->getDoctrine()->getManager();
        try {
            $cart = $em->getRepository("AppBundle:Cart")
                ->find($cartId);
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database");
        }

        if ( is_null($cart) )
            throw $this->createNotFoundException("Cart not found");
        try {
            $product = $em->getRepository("AppBundle:Product")
                ->find($productId);
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database");
        }
        if ( is_null($product) )
            throw $this->createNotFoundException("Product not found");


        try {
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
                }
            }

            $cart->setTotalPrice( $cart->getTotalPrice()+$deltaQuantity*$product->getPrice() );

            $em->flush();

            return new JsonResponse(array(
                "quantity"=>$cartProduct->getQuantity(),
                "totalPrice"=>$cart->getTotalPrice()
            ));
        } catch (\Exception $e) {
            throw new \Exception("There was a problem with the database");
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
        if ( !is_integer($cartId) )
            throw new \Exception("Wrong type of parameter: $cartId. Should be integer.");
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