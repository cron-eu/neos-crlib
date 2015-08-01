<?php
/**
 * Created by PhpStorm.
 * User: lazarrs
 * Date: 01.08.15
 * Time: 10:17
 */

namespace CRON\CRLib\Utility;
use Doctrine\ORM\QueryBuilder;
use TYPO3\Flow\Annotations as Flow;

class NodeQuery {
	/**
	 * Doctrine's Entity Manager. Note that "ObjectManager" is the name of the related
	 * interface ...
	 *
	 * @Flow\Inject
	 * @var \Doctrine\Common\Persistence\ObjectManager
	 */
	protected $entityManager;

	/** @var QueryBuilder $queryBuilder */
	public $queryBuilder;

	public function initializeObject() {
		$this->queryBuilder = $this->entityManager->createQueryBuilder();
		$this->queryBuilder->select('n')
		                   ->from('TYPO3\TYPO3CR\Domain\Model\NodeData', 'n')
		                   ->where('n.workspace IN (:workspaces)')
		                   ->setParameter('workspaces', 'live');
	}

	/**
	 * @param array|string $types NodeType names
	 */
	public function addTypeConstraint($types) {
		if (is_string($types)) $types = preg_split('/,\s*/', $types);
		$this->queryBuilder->andWhere('n.nodeType IN (:includeNodeTypes)')
		                   ->setParameter('includeNodeTypes', $types);
	}

	/**
	 * @param string $path node starting path
	 */
	public function addPathConstraint($path) {
		$this->queryBuilder->andWhere('n.path LIKE :path')
		                   ->setParameter('path', $path.'%');
	}

	/**
	 * @param string $term Search Term to search in properties using LIKE
	 */
	public function addSearchTermConstraint($term) {
		$this->queryBuilder->andWhere('n.properties LIKE :term')
		                   ->setParameter('term', '%'.$term.'%');
	}

	/**
	 * @param string $identifier
	 */
	public function addIdentifierConstraint($identifier) {
		$this->queryBuilder->andWhere('n.identifier = :identifier')
		                   ->setParameter('identifier', $identifier);
	}

	public function getQuery() {
		return $this->queryBuilder->getQuery();
	}

	/**
	 * @return int
	 */
	public function getCount() {
		$this->queryBuilder->select('COUNT(n)');
		$count = (int)$this->queryBuilder->getQuery()->getSingleScalarResult();
		$this->queryBuilder->select('n');
		return $count;
	}

}