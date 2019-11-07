<?php


namespace creemedia\Bundle\eZcontentbirdBundle\Common;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;

class DatabaseService
{

	const TABLE_ARTICLE = 'cm_articles';

	public function __construct(Container $container)
	{
		$this->container = $container;
		$this->conn = $container->get('database_connection');
		$this->createTables();
	}

	public function createTables()
	{
		$schemaManager = $this->conn->getSchemaManager();

		if ($schemaManager->tablesExist([self::TABLE_ARTICLE]) === TRUE) {
			return;
		}

		$schema = new \Doctrine\DBAL\Schema\Schema();
		$cityTable = $schema->createTable(self::TABLE_ARTICLE);

		$cityTable->addColumn('contentid', 'string', ['length' => 255]);
		$cityTable->addColumn('title', 'string', ['length' => 255]);
		$cityTable->addColumn('text', 'text');
		$cityTable->addColumn('summary', 'text');

		$cityTable->addColumn('image', 'string', ['length' => 255]);
		$cityTable->addColumn('authors', 'string', ['length' => 255]);

		$cityTable->addColumn('parentid', 'string', ['length' => 255]);
		$cityTable->addColumn('keywords', 'string', ['length' => 255]);
		$cityTable->addColumn('userid', 'string', ['length' => 255]);

		$cityTable->addColumn('created', 'date');
		$cityTable->addColumn('modified', 'date');

		$cityTable->setPrimaryKey(['contentid']);

		$sql = $schema->toSql($this->conn->getDatabasePlatform());
		$stmt = $this->conn->prepare($sql[0]);
		$stmt->execute();
	}

	public function updateRow(string $contentid, string $title, string $text, string $summary, string $parentid, string $keywords, string $userid)
	{
		/** @var \Doctrine\DBAL\Query\QueryBuilder $queryBuilder */
		$queryBuilder = $this->conn->createQueryBuilder();
		$queryBuilder
			->update(self::TABLE_ARTICLE)
			->from(self::TABLE_ARTICLE)
			->set('contentid', '?')
			->set('title', '?')
			->set('text', '?')
			->set('summary', '?')
			->set('parentid', '?')
			->set('keywords', '?')
			->set('userid', '?')
			->set('updated', '?')
			->setParameter(0, $contentid)
			->setParameter(1, $title)
			->setParameter(2, $text)
			->setParameter(2, $summary)
			->setParameter(3, $parentid)
			->setParameter(4, $keywords)
			->setParameter(5, $userid)
			->setParameter(6, date('y-m-d'))
			->execute();
	}

	public function insertRow(string $contentid, string $title, string $text, string $summary, string $parentid, string $keywords, string $userid, string $image)
	{
		/** @var \Doctrine\DBAL\Query\QueryBuilder $queryBuilder */
		$queryBuilder = $this->conn->createQueryBuilder();
		$queryBuilder
			->insert(self::TABLE_ARTICLE)
			->values(
				[
					'contentid' => '?',
					'title' => '?',
					'text' => '?',
					'summary' => '?',
					'parentid' => '?',
					'keywords' => '?',
					'userid' => '?',
					'image' => '?',
					'created' => '?'
				]
			)
			->setParameter(0, $contentid)
			->setParameter(1, $title)
			->setParameter(2, $text)
			->setParameter(3, $summary)
			->setParameter(4, $parentid)
			->setParameter(5, $keywords)
			->setParameter(6, $userid)
			->setParameter(7, $image)
			->setParameter(8, date('y-m-d'))
			->execute();
	}

	public function select(string $contentId)
	{
		/** @var QueryBuilder $queryBuilder */
		$queryBuilder = $this->conn->createQueryBuilder();
		$result = $queryBuilder
			->select('*')
			->from(self::TABLE_ARTICLE)
			->andWhere('contentid = ' . $queryBuilder->createNamedParameter($contentId))
			->execute()
			->fetch();

		return $result;
	}

}
