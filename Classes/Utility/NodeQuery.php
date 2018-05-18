<?php
/**
 * Created by PhpStorm.
 * User: lazarrs
 * Date: 01.08.15
 * Time: 10:17
 */

namespace CRON\CRLib\Utility;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Unicode\Functions as Unicode;

/**
 * @property mixed|null initialTypeConstraint
 * @property null|string initialPathConstraint
 * @property null|string initialSearchTermConstraint
 * @property string workspace
 */
class NodeQuery
{
    /**
     * Doctrine's Entity Manager. Note that "ObjectManager" is the name of the related
     * interface ...
     *
     * @Flow\Inject
     * @var \Doctrine\Common\Persistence\ObjectManager
     */
    protected $entityManager;

    /**
     * Convenience Constructor to initialize the object with some constraints
     *
     * @param mixed $nodeTypeFilter csv list of NodeTypes to filter
     * @param null|string $path filter by path
     * @param null|string $searchTerm search term
     * @param string $workspace workspace
     *
     */
    function __construct($nodeTypeFilter = null, $path = null, $searchTerm = null, $workspace = 'live')
    {
        // we save it as property to apply the constraints later in initializeObject()
        $this->initialTypeConstraint = $nodeTypeFilter;
        $this->initialPathConstraint = $path;
        $this->initialSearchTermConstraint = $searchTerm;
        $this->workspace = $workspace;
    }

    /** @var QueryBuilder $queryBuilder */
    public $queryBuilder;

    public function initializeObject()
    {
        /** @var EntityManager $em */
        $em = $this->entityManager;
        $this->queryBuilder = $em->createQueryBuilder();
        $this->queryBuilder->select('n')
                           ->from('Neos\ContentRepository\Domain\Model\NodeData', 'n');

        if (!empty($this->workspace)) {
            $this->queryBuilder
                ->where('n.workspace IN (:workspaces)')
                ->setParameter('workspaces', $this->workspace);
        }

        if ($this->initialPathConstraint) {
            $this->addPathConstraint($this->initialPathConstraint);
        }
        if ($this->initialTypeConstraint) {
            $this->addTypeConstraint($this->initialTypeConstraint);
        }
        if ($this->initialSearchTermConstraint) {
            $this->addSearchTermConstraint($this->initialSearchTermConstraint);
        }
    }

    /**
     * @param array|string $types NodeType names
     */
    public function addTypeConstraint($types)
    {
        if (is_string($types)) {
            $types = preg_split('/,\s*/', $types);
        }
        $this->queryBuilder->andWhere('n.nodeType IN (:includeNodeTypes)')
                           ->setParameter('includeNodeTypes', $types);
    }

    /**
     * @param string $path node starting path
     */
    public function addPathConstraint($path)
    {
        $this->queryBuilder->andWhere('n.path LIKE :path')
                           ->setParameter('path', $path . '%');
    }

    /**
     * @param string $term Search Term to search in properties using LIKE
     */
    public function addSearchTermConstraint($term)
    {
        // Convert to lowercase, then to json, and then trim quotes from json to have valid JSON escaping.
        $likeParameter = '%' .
            str_replace('\\', '\\\\', // escape all \
                trim(
                    json_encode(Unicode::strtolower($term), JSON_UNESCAPED_UNICODE),
                    '"'
                )) . '%';
        $this->queryBuilder->andWhere('n.properties LIKE :term')
                           ->setParameter('term', $likeParameter);
    }

    /**
     * @param string $identifier
     */
    public function addIdentifierConstraint($identifier)
    {
        $this->queryBuilder->andWhere('n.identifier LIKE :identifier')
                           ->setParameter('identifier', $identifier . '%');
    }

    /**
     * @param string $sort The ordering expression.
     * @param string $order The ordering direction.
     */
    public function setOrderBy($sort, $order)
    {
        $this->queryBuilder->orderBy('n.' . $sort, $order);
    }

    /**
     * Gets the Doctrine ORM Query, results ordered by path ASC
     *
     * @return \Doctrine\ORM\Query
     */
    public function getQuery()
    {
        $this->setOrderBy('path', 'ASC');
        return $this->queryBuilder->getQuery();
    }

    /**
     * @return int
     */
    public function getCount()
    {
        $this->queryBuilder->select('COUNT(n)');
        $count = (int)$this->queryBuilder->getQuery()->getSingleScalarResult();
        $this->queryBuilder->select('n');

        return $count;
    }

    /**
     * Delete all nodes matching the current criteria
     *
     * @return int number of nodes deleted
     */
    public function deleteAll()
    {
        $this->queryBuilder->delete();
        $count = $this->queryBuilder->getQuery()->execute();
        $this->queryBuilder->select('n');

        return $count;
    }

}
