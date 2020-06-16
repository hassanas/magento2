<?php

namespace Magento\Test\Integrity\Dependency;

use Magento\Framework\App\Bootstrap;

/**
 * Class GraphQlSchemaDependencyProvider
 * @package Magento\Test\Integrity\Dependency
 */
class GraphQlSchemaDependencyProvider extends DependencyProvider
{
    /**
     * @var array
     */
    private $parsedSchema = [];

    /**
     * GraphQlSchemaDependencyProvider constructor.
     */
    public function __construct()
    {
        $this->getGraphQlSchemaDeclaration();
    }

    /**
     * Provide declared dependencies between modules based on the declarative schema configuration.
     *
     * @param string $moduleName
     * @return array
     * @throws \Exception
     */
    public function getDeclaredExistingModuleDependencies(string $moduleName): array
    {
        $this->initDeclaredDependencies();
        $dependencies = $this->getDependenciesFromSchema($moduleName);
        $declared = $this->getDeclaredDependencies($moduleName, self::TYPE_HARD, self::MAP_TYPE_DECLARED);
        return array_unique(array_values(array_intersect($declared, $dependencies)));
    }

    /**
     * Provide undeclared dependencies between modules based on the declarative schema configuration.
     *
     * [
     *     $dependencyId => [$module1, $module2, $module3 ...],
     *     ...
     * ]
     *
     * @param string $moduleName
     * @return array
     * @throws \Exception
     */
    public function getUndeclaredModuleDependencies(string $moduleName): array
    {
        $this->initDeclaredDependencies();
        $dependencies = $this->getDependenciesFromSchema($moduleName);
        return $this->collectDependencies($moduleName, $dependencies);
    }

    /**
     * Retrieve array of dependency items.
     *
     * @param $module
     * @param $type
     * @param $mapType
     * @return array
     */
    protected function getDeclaredDependencies(string $module, string $type, string $mapType): array
    {
        return $this->mapDependencies[$module][$type][$mapType] ?? [];
    }

    /**
     * Get parsed GraphQl schema
     *
     * @return array
     */
    private function getGraphQlSchemaDeclaration(): array
    {
        if (!$this->parsedSchema) {
            $objectManager = Bootstrap::create(BP, $_SERVER)->getObjectManager();
            $reader = $objectManager->create(\Magento\Framework\GraphQlSchemaStitching\GraphQlReader::class);
            $this->parsedSchema = $reader->read();
        }

        return $this->parsedSchema;
    }

    /**
     * Get dependencies from GraphQl schema
     *
     * @param string $moduleName
     * @return array
     */
    private function getDependenciesFromSchema(string $moduleName): array
    {
        $schema = $this->parsedSchema;

        $dependencies = [];

        foreach ($schema as $typeName => $type) {
            if (isset($type['module']) && $type['module'] == $moduleName && isset($type['implements'])) {
                foreach ($type['implements'] as $interfaceName => $interfaceData) {
                    $dependOnModule = $schema[$interfaceName]['module'];
                    if ($dependOnModule != $moduleName) {
                        $dependencies[] = $dependOnModule;
                    }
                }

            }
        }
        return array_unique($dependencies);
    }

    /**
     * Collect module dependencies.
     *
     * @param string $currentModuleName
     * @param array $dependencies
     * @return array
     */
    private function collectDependencies(string $currentModuleName, array $dependencies = []): array
    {
        if (empty($dependencies)) {
            return [];
        }
        $declared = $this->getDeclaredDependencies($currentModuleName, self::TYPE_HARD, self::MAP_TYPE_DECLARED);
        $checkResult = array_intersect($declared, $dependencies);

        if (empty($checkResult)) {
            $this->addDependencies(
                $currentModuleName,
                self::TYPE_HARD,
                self::MAP_TYPE_FOUND,
                [$currentModuleName => $dependencies]
            );
        }

        return $this->getDeclaredDependencies($currentModuleName, self::TYPE_HARD, self::MAP_TYPE_FOUND);
    }
}
