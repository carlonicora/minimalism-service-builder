<?php
namespace CarloNicora\Minimalism\Services\Builder;

use CarloNicora\JsonApi\Objects\ResourceObject;
use CarloNicora\Minimalism\Abstracts\AbstractService;
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
use CarloNicora\Minimalism\Services\DataMapper\Interfaces\DataObjectInterface;
use CarloNicora\Minimalism\Services\Path;
use Exception;
use RuntimeException;

class Builder extends AbstractService implements BuilderInterface
{
    /** @var ServiceInterface|null  */
    private ?ServiceInterface $transformer=null;

    /**
     * @param DataInterface $data
     * @param DataMapper $mapper
     * @param EncrypterInterface $encrypter
     * @param Path $path
     * @param CacheInterface|null $cache
     */
    public function __construct(
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
                $dataLoader = $this->objectFactory->createSimpleObject(className: $function->getClassName(), parameters: new ModelParameters());

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
     * @param array|DataObjectInterface[]|DataObjectInterface $data
     * @param int $relationshipLevel
     * @param array $additionalRelationshipData
     * @return array
     * @throws Exception
     */
    public function buildByData(
        string $resourceTransformerClass,
        array|DataObjectInterface $data,
        int $relationshipLevel=1,
        array $additionalRelationshipData=[],
    ): array
    {
        $data = !is_array($data) ? $data->export() : $data;

        if (empty($data)) {
            return [];
        }

        $response = [];
        if (array_is_list($data)) {
            foreach ($data as $record) {
                $record = !is_array($record) ? $record->export() : $record;
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
     * @param array|DataObjectInterface $data
     * @param int $relationshipLevel
     * @param array $additionalRelationshipData
     * @return ResourceObject
     * @throws Exception
     */
    private function createBuilder(
        string $builderClassName,
        array|DataObjectInterface $data,
        int $relationshipLevel,
        array $additionalRelationshipData=[],
    ): ResourceObject
    {
        if (!is_array($data))
        {
            $data = $data->export();
        }

        /** @var ResourceBuilderInterface $builder */
        $builder = new $builderClassName(
            objectFactory: $this->objectFactory,
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
                    } elseif (!$relationship->isOptional()) {
                        throw new RuntimeException('Required parameter(s) for ' .  $relationship->getName() . ' relationship missed');
                    }
                }

                if (empty($parameterValues) && $relationship->isOptional()) {
                    continue;
                }

                $relationship->getDataFunction()->replaceParameters($parameterValues);

                if ($relationship->getDataFunction()->getType() === DataFunctionInterface::TYPE_TABLE){
                    /** @var array|DataObjectInterface $relationshipData */
                    $relationshipData = $this->data->readByDataFunction(
                        $relationship->getDataFunction()
                    );
                } else {
                    $dataLoader = $this->objectFactory->createSimpleObject(
                        className: $relationship->getDataFunction()->getClassName(),
                        parameters: new ModelParameters(),
                    );

                    /** @var array|DataObjectInterface $relationshipData */
                    $relationshipData = $dataLoader->{$relationship->getDataFunction()->getFunctionName()}(...$relationship->getDataFunction()->getParameters());
                }

                $relationshipData = !is_array($relationshipData) ? $relationshipData->export() : $relationshipData;

                if ((empty($relationshipData) || (array_is_list($relationshipData) && empty($relationshipData[0])))
                    && !$relationship->isOptional()
                ) {
                    throw new RuntimeException('Required ' . $relationship->getName() . ' relationship data missed');
                }

                if (array_key_exists(0, $relationshipData)) {
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
                } elseif (!empty($relationshipData)){
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