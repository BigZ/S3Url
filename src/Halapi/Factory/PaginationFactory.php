<?php

namespace Halapi\Factory;

use Halapi\ObjectManager\ObjectManagerInterface;
use Halapi\Representation\PaginatedRepresentation;
use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Halapi\UrlGenerator\UrlGeneratorInterface;

/**
 * Class PaginationFactory.
 *
 * @author Romain Richard
 */
class PaginationFactory
{
    /**
     * @var ObjectManagerInterface
     */
    public $objectManager;

    /**
     * @var UrlGeneratorInterface
     */
    public $urlGenerator;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var string
     */
    private $pagerStrategy;

    /**
     * PaginationFactory constructor.
     *
     * @param UrlGeneratorInterface  $urlGenerator
     * @param ObjectManagerInterface $objectManager
     * @param RequestStack           $requestStack
     * @param string                 $pagerStrategy
     */
    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        ObjectManagerInterface $objectManager,
        RequestStack $requestStack,
        $pagerStrategy = 'DoctrineORM'
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->objectManager = $objectManager;
        $this->requestStack = $requestStack;
        $this->setPagerStrategy($pagerStrategy);
    }

    /**
     * Get a paginated representation of a collection of entities.
     * Your repository for the object $className must implement the 'findAllSorted' method
     * @param string $className
     *
     * @return PaginatedRepresentation
     */
    public function getRepresentation($className)
    {
        $shortName = (new \ReflectionClass($className))->getShortName();
        list($page, $limit, $sorting, $filterValues, $filerOperators) = array_values($this->addPaginationParams());
        $results = $this->objectManager->findAllSorted($className, $sorting, $filterValues, $filerOperators);

        $pagerAdapter = $this->getPagerAdapter($results);
        $pager = new Pagerfanta($pagerAdapter);
        $pager->setMaxPerPage($limit);
        $pager->setCurrentPage($page);

        return new PaginatedRepresentation(
            $page,
            $limit,
            [
                'self' => $this->getPaginatedRoute($shortName, $limit, $page, $sorting),
                'first' => $this->getPaginatedRoute($shortName, $limit, 1, $sorting),
                'next' => $this->getPaginatedRoute(
                    $shortName,
                    $limit,
                    $page < $pager->getNbPages() ? $page + 1 : $pager->getNbPages(),
                    $sorting
                ),
                'last' => $this->getPaginatedRoute($shortName, $limit, $pager->getNbPages(), $sorting),
            ],
            (array) $pager->getCurrentPageResults()
        );
    }

    /**
     * @param string $pagerStrategy
     */
    public function setPagerStrategy($pagerStrategy)
    {
        if (!class_exists('Pagerfanta\Adapter\\'.$pagerStrategy.'Adapter')) {
            throw new \InvalidArgumentException(sprintf(
                'No adapter named %s found in %s namespace',
                'Doctrine'.$pagerStrategy.'Adapter',
                'Pagerfanta\Adapter'
            ));
        }

        $this->pagerStrategy = $pagerStrategy;
    }

    /**
     * @param array $results
     *
     * @return AdapterInterface
     */
    private function getPagerAdapter($results)
    {
        $adapterClassName = 'Pagerfanta\Adapter\\'.$this->pagerStrategy.'Adapter';

        return new $adapterClassName(...$results);
    }

    /**
     * Get the pagination parameters, filtered.
     *
     * @return array
     */
    private function addPaginationParams()
    {
        $resolver = new OptionsResolver();

        $resolver->setDefaults(array(
            'page' => '1',
            'limit' => '20',
            'sorting' => [],
            'filtervalue' => [],
            'filteroperator' => [],
        ));

        $resolver->setAllowedTypes('page', ['NULL', 'string']);
        $resolver->setAllowedTypes('limit', ['NULL', 'string']);
        $resolver->setAllowedTypes('sorting', ['NULL', 'array']);
        $resolver->setAllowedTypes('filtervalue', ['NULL', 'array']);
        $resolver->setAllowedTypes('filteroperator', ['NULL', 'array']);

        $request = $this->requestStack->getMasterRequest();

        return $resolver->resolve(array_filter([
            'page' => $request->query->get('page'),
            'limit' => $request->query->get('limit'),
            'sorting' => $request->query->get('sorting'),
            'filtervalue' => $request->query->get('filtervalue'),
            'filteroperator' => $request->query->get('filteroperator'),
        ]));
    }

    /**
     * Return the url of a resource based on the 'get_entity' route name convention.
     *
     * @param string $name
     * @param $limit
     * @param $page
     * @param $sorting
     *
     * @return string
     */
    private function getPaginatedRoute($name, $limit, $page, $sorting)
    {
        return $this->urlGenerator->generate(
            'get_'.strtolower($name).'s',
            [
                'sorting' => $sorting,
                'page' => $page,
                'limit' => $limit,
            ]
        );
    }
}
