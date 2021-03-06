<?php
/*
 * This file is part of Swagger Mock.
 *
 * (c) Igor Lazarev <strider2038@yandex.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\OpenAPI\Parsing;

use App\Mock\Parameters\MockResponse;
use App\Mock\Parameters\MockResponseMap;
use App\OpenAPI\ErrorHandling\ErrorHandlerInterface;
use App\OpenAPI\SpecificationObjectMarkerInterface;
use Psr\Log\LoggerInterface;

/**
 * @author Igor Lazarev <strider2038@yandex.ru>
 */
class ResponseCollectionParser implements ParserInterface
{
    /** @var ParserInterface */
    private $responseParser;

    /** @var ReferenceResolvingParser */
    private $resolvingParser;

    /** @var ErrorHandlerInterface */
    private $errorHandler;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        ParserInterface $responseParser,
        ReferenceResolvingParser $resolvingParser,
        ErrorHandlerInterface $errorHandler,
        LoggerInterface $logger
    ) {
        $this->responseParser = $responseParser;
        $this->resolvingParser = $resolvingParser;
        $this->errorHandler = $errorHandler;
        $this->logger = $logger;
    }

    public function parsePointedSchema(SpecificationAccessor $specification, SpecificationPointer $pointer): SpecificationObjectMarkerInterface
    {
        $responses = new MockResponseMap();
        $responseSchemas = $specification->getSchema($pointer);

        foreach ($responseSchemas as $statusCode => $responseSpecification) {
            $responsePointer = $pointer->withPathElement($statusCode);
            $isValid = $this->validateResponse($statusCode, $responseSpecification, $responsePointer);

            if ($isValid) {
                /** @var MockResponse $response */
                $response = $this->resolvingParser->resolveReferenceAndParsePointedSchema($specification, $responsePointer, $this->responseParser);
                $parsedStatusCode = $this->parseStatusCode($statusCode);
                $response->statusCode = $parsedStatusCode;
                $responses->put($parsedStatusCode, $response);

                $this->logger->debug(
                    sprintf('Response with status code "%s" was parsed.', $response->statusCode),
                    ['path' => $responsePointer->getPath()]
                );
            }
        }

        return $responses;
    }

    private function validateResponse($statusCode, $responseSpecification, SpecificationPointer $pointer): bool
    {
        $isValid = true;

        if (!\is_int($statusCode) && 'default' !== $statusCode) {
            $isValid = false;
            $this->errorHandler->reportError('Invalid status code. Must be integer or "default".', $pointer);
        }

        if (!\is_array($responseSpecification)) {
            $isValid = false;
            $this->errorHandler->reportError('Invalid response specification.', $pointer);
        }

        return $isValid;
    }

    private function parseStatusCode($statusCode): int
    {
        $parsedStatusCode = (int) $statusCode;

        if (0 === $parsedStatusCode) {
            $parsedStatusCode = MockResponse::DEFAULT_STATUS_CODE;
        }

        return $parsedStatusCode;
    }
}
