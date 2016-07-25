<?php
/**
 * Created by PhpStorm.
 * User: piotr.grzegorzewski
 * Date: 25/07/2016
 * Time: 17:21
 */

namespace AppBundle\Controller;


use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

class CartController extends Controller
{
    /**
     * @Route("/cart", name="cart_create")
     * @Method("POST")
     * @return JsonResponse
     */
    public function createAction() {
        // TODO: Error codes in case something goes wrong
        return new JsonResponse(array(
            "id"=>1
        ));
    }

    /**
     * @Route("/cart/{cartId}", name="cart_remove")
     * @Method("DELETE")
     * @param int $cartId
     * @return JsonResponse
     */
    public function removeAction($cartId) {
        // TODO: Error codes in case something goes wrong
        return new JsonResponse(array(
            "id"=>1
        ));
    }

    /**
     * @Route("/cart/{cartId}/{$productId}", name="cart_add_product")
     * @Method("POST")
     * @param int $cartId
     * @param int $productId
     * @return JsonResponse
     */
    public function addProductAction($cartId, $productId) {
        // TODO: Error codes in case something goes wrong
        return new JsonResponse(array(
            "id"=>1
        ));
    }


    /**
     * @Route("/cart/{cartId}/{$productId}", name="cart_remove_product")
     * @Method("DELETE")
     * @param int $cartId
     * @param int $productId
     */
    public function removeProductAction($cartId, $productId) {

    }

    /**
     * @Route("/cart/{cartId}/{$productId}/{$quantity}", name="cart_update_product")
     * @Method("PUT")
     * @param int $cartId
     * @param int $productId
     * @param int $quantity
     */
    public function setProductAction($cartId, $productId, $quantity) {

    }

    /**
     * @Route("/cart/{id}", name="cart_list_products")
     * @Method("GET")
     */
    public function listProductsAction() {

    }

}