<?php
namespace CarloNicora\Minimalism\Services\Builder;

use CarloNicora\JsonApi\Objects\ResourceObject;
use CarloNicora\Minimalism\Abstracts\AbstractService;
use CarloNicora\Minimalism\Factories\MinimalismFactories;
use CarloNicora\Minimalism\Interfaces\Cache\Enums\CacheType;
use CarloNicora\Minimalism\Interfaces\Cache\Interfaces\CacheInterface;
use CarloNicora\Minimalism\Interfaces\Data\Interfaces\DataFunctionInterface;
use CarloNicora\Minimalism\Interfaces\Data\Interfaces\DataInterface;
use CarloNicora\Minimalism\Interfaces\Encrypter\Interfaces\EncrypterInterface;
use CarloNicora\Minimalism\Objects\ModelParameters;
use CarloNicora\Minimalism\Services\Builder\Interfaces\ResourceBuilderInterface;
use CarloNicora\Minimalism\Services\Builder\Objects\RelationshipBuilder;
use CarloNicora\Minimalism\Interfaces\ServiceInterface;
use CarloNicora\Minimalism\Services\DataMapper\DataMapper;
use CarloNicora\Minimalism\Services\DataMapper\Interfaces\BuilderInterface;
use CarloNicora\Minimalism\Services\Path;
use Exception;
use RuntimeException;

class Builder extends AbstractService implements BuilderInterface
{
    /** @var ServiceInterface|null  */
    private ?ServiceInterface $transformer=null;

    public function __construct(
        private MinimalismFactories $minimalismFactories,
        private DataInterface $data,
        private DataMapper $mapper,
        private EncrypterInterface $encrypter,
        private Path $path,
        private ?CacheInterface $cache=null,

    )
    {
        parent::__construct();

        $this->mapper->setBuilder($this);
    }

    /**
     * @param ServiceInterface $transformer
     */
    public function setTransformer(ServiceInterface $transformer): void
    {
        $this->transformer = $transformer;
    }

    /**
     * @param string $resourceTransformerClass
     * @param DataFunctionInterface $function
     * @param int $relationshipLevel
     * @param array $additionalRelationshipData
     * @return array
     * @throws Exception
     */
    public function build(
        string $resourceTransformerClass,
        DataFunctionInterface $function,
        int $relationshipLevel=1,
        array $additionalRelationshipData=[],
    ): array
    {
        $response = null;

        if ($this->cache !== null
            &&
            $function->getCacheBuilder() !== null
            &&
            $this->cache->useCaching()
        ) {
            $response = $this->cache->read(
                builder: $function->getCacheBuilder()??throw new RuntimeException('Error using the cache builder', 500),
                cacheBuilderType: CacheType::Json,
            );
            if ($response !== null){
                $response = unserialize($response, [true]);
            }
        }

        if ($response === null) {
            if ($function->getType() === DataFunctionInterface::TYPE_TABLE){
                $data = $this->data->readByDataFunction(
                    $function
                );
            } else {
                $dataLoader = $this->minimalismFactories->getObjectFactory()->createSimpleObject(className: $function->getClassName(), parameters: new ModelParameters());


                $parameters = $function->getParameters() ?? [];
                $data = $dataLoader->{$function->getFunctionName()}(...$parameters);
            }

            $response = $this->buildByData(
                resourceTransformerClass: $resourceTransformerClass,
                data: $data,
                relationshipLevel: $relationshipLevel,
                additionalRelationshipData: $additionalRelationshipData,
            );

            if ($this->cache !== null && $function->getCacheBuilder() !== null && $this->cache->useCaching()) {
                $this->cache->save(
                    builder: $function->getCacheBuilder()??throw new RuntimeException('Error using the cache builder', 500),
                    data: serialize($response),
                    cacheBuilderType: CacheType::Json,
                );
            }
        }

        return $response;
    }

    /**
     * @param string $resourceTransformerClass
     * @param array $data
     * @param int $relationshipLevel
     * @param array $additionalRelationshipData
     * @return array
     * @throws Exception
     */
    public function buildByData(
        string $resourceTransformerClass,
        array $data,
        int $relationshipLevel=1,
        array $additionalRelationshipData=[],
    ): array
    {
        if (empty($data)) {
            return [];
        }

        $response = [];
        if (array_key_exists(0, $data)) {
            foreach ($data ?? [] as $record) {
                $response[] = $this->createBuilder(
                    builderClassName: $resourceTransformerClass,
                    data: $record,
                    relationshipLevel: $relationshipLevel,
                    additionalRelationshipData: $additionalRelationshipData,
                );
            }
        } else {
            $response[] = $this->createBuilder(
                builderClassName: $resourceTransformerClass,
                data: $data,
                relationshipLevel: $relationshipLevel,
                additionalRelationshipData: $additionalRelationshipData,
            );
        }

        return $response;
    }

    /**
     * @param string $builderClassName
     * @param array $data
     * @param int $relationshipLevel
     * @param array $additionalRelationshipData
     * @return ResourceObject
     * @throws Exception
     */
    private function createBuilder(
        string $builderClassName,
        array $data,
        int $relationshipLevel,
        array $additionalRelationshipData=[],
    ): ResourceObject
    {
        /** @var ResourceBuilderInterface $builder */
        $builder = new $builderClassName(
            path: $this->path,
            encrypter: $this->encrypter,
            transformer: $this->transformer,
        );
        $builder->setAttributes($data);
        $builder->setLinks($data);
        $builder->setMeta($data);

        $response = $builder->getResourceObject();

        if ($relationshipLevel > 0) {
            $relationshipLevel--;
            /** @var RelationshipBuilder $relationship */
            foreach ($builder->getRelationshipReaders() ?? [] as $relationship) {
                $builderClassName = $relationship->getBuilderClassName();
                if (array_key_exists($relationship->getName(), $additionalRelationshipData)){
                    $relationshipSpecificData = $additionalRelationshipData[$relationship->getName()];
                } else {
                    $relationshipSpecificData = [];
                }

                $parameterValues = [];
                foreach ($relationship->getDataFunction()->getParameters() ?? [] as $parameterKey=>$parameterValue){
                    if (array_key_exists($parameterValue, $data) && $data[$parameterValue] !== null){
                        $parameterValues[$parameterKey] = $data[$parameterValue];
                    } elseif (array_key_exists($parameterValue, $relationshipSpecificData) && $relationshipSpecificData[$parameterValue] !== null){
                        $parameterValues[$parameterKey] = $relationshipSpecificData[$parameterValue];
                    } elseif (false === $relationship->isOptional()) {
                        throw new RuntimeException('Required parameter(s) for ' .  $relationship->getName() . ' relationship missed');
                    }
                }

                if (empty($parameterValues) && $relationship->isOptional()) {
                    continue;
                }

                $relationship->getDataFunction()->replaceParameters($parameterValues);

                if ($relationship->getDataFunction()->getType() === DataFunctionInterface::TYPE_TABLE){
                    $relationshipData = $this->data->readByDataFunction(
                        $relationship->getDataFunction()
                    );
                } else {
                    $dataLoader = $this->minimalismFactories->getObjectFactory()->createSimpleObject(
                        className: $relationship->getDataFunction()->getClassName(),
                        parameters: new ModelParameters(),
                    );

                    $relationshipData = $dataLoader->{$relationship->getDataFunction()->getFunctionName()}(...$relationship->getDataFunction()->getParameters());
                }

                if ((empty($relationshipData) || (array_key_exists(0, $relationshipData) && empty($relationshipData[0])))
                    && $relationship->isOptional() === false
                ) {
                    throw new RuntimeException('Required ' . $relationship->getName() . ' relationship data missed');
                }

                if (isset($relationshipData) && array_key_exists(0, $relationshipData)) {
                    if (empty($relationshipData[0])) {
                        continue;
                    }

                    foreach ($relationshipData ?? [] as $record) {
                        $resourceLinkage = $response->relationship(
                            $relationship->getName()
                        )->resourceLinkage;

                        $resourceLinkage->add(
                            $this->createBuilder(
                                builderClassName: $builderClassName,
                                data: $record,
                                relationshipLevel: $relationship->isDontLoadChildren() ? 0 : $relationshipLevel
                            )
                        );

                        $resourceLinkage->forceResourceList($relationship->isList());
                    }
                } elseif (false === empty($relationshipData)){
                    $response->relationship(
                        $relationship->getName()
                    )->resourceLinkage->add(
                        $this->createBuilder(
                            builderClassName: $builderClassName,
                            data: $relationshipData,
                            relationshipLevel: $relationship->isDontLoadChildren() ? 0 : $relationshipLevel
                        )
                    );
                }
            }
        }

        return $response;
    }
}