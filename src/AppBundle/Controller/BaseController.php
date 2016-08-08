<?php
/**
 * Created by PhpStorm.
 * User: piotr.grzegorzewski
 * Date: 05/08/2016
 * Time: 17:11
 */

namespace AppBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class BaseController extends Controller
{


    /**
     * @param mixed $data
     * @param int $statusCode
     * @return Response
     */
    protected function createApiResponse($data, $statusCode = 200)
    {
        return new Response(
            json_encode($data),
            $statusCode,
            array(
                'Content-Type' => 'application/vnd.api+json'
            )
        );
    }

    /**
     * @param ConstraintViolationListInterface $errors
     * @param string $title
     * @param int $statusCode
     * @return Response
     */
    protected function createValidationErrorResponse($errors, $title="Validation error", $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY) {

        $data = [];
        $i = 0;
        while ( $errors->has($i) ) {
            $e = $errors->get($i);
            $data[] = array(
                "status" => $e->getCode(),
                "title" => $title,
                "detail" => $e->getMessage()
            );
            $i++;
        }

        return $this->createApiResponse(["errors"=>$data], $statusCode);
    }


    /**
     * @param string $elementType Type of the element which was not found (for example: Cart, Product)
     * @param integer $elementId Requested ID that was not found
     * @param string $customDescription
     * @return Response
     */
    protected function createNotFoundResponse($elementType, $elementId, $customDescription="") {
/*
        {
            "errors": {
                "id": 1,
                "status": 404,
                "code": "not-found",
                "title": "Cart not found",
                "detail": "Cart 1 was not found on this server"
            }
        }
*/
        $data = [
            "id" => 1,
            "status" => 404,
            "code" => "not-found",
            "title" => $elementType . " not found",
            "detail" => (($customDescription)?$customDescription:$elementType . " ". $elementId . " was not found on this server")
        ];
        return $this->createApiResponse(["errors"=>$data], Response::HTTP_NOT_FOUND);
    }

    protected function createErrorResponse($title, $detail, $statusCode=Response::HTTP_BAD_REQUEST) {
        $data = [
            "status" => $statusCode,
            "title" => $title,
            "detail" => $detail
        ];
        return $this->createApiResponse(["errors"=>$data], $statusCode);
    }
}