<?php

namespace DbalUtil\Connection\Pagerfanta;

use DbalUtil\Connection\ConnectionTrait;
use Pagerfanta\Adapter\DoctrineDbalAdapter;
use Pagerfanta\Pagerfanta;
use PagerfantaAdapters\Doctrine\DBAL\TwoModifiers;


trait PagedQueryTrait
{
    use ConnectionTrait;

    public function getManyToManyWherePager($base_table, $base_id,
        $link_base_id, $link_table, $link_distant_id,
        $distant_id, $distant_table, array $where, $orderby='')
    // PageFanta dependency should be isolated!
    {
        $queryBuilder = $this->getManyToManyWhereQueryBuilder($base_table, $base_id, $link_base_id, $link_table, $link_distant_id, $distant_id, $distant_table, $where, $orderby);
        // $unorderedQueryBuilder = $this->getManyToManyWhereQueryBuilder($base_table, $base_id, $link_base_id, $link_table, $link_distant_id, $distant_id, $distant_table, $where);
        // TODO: do not make it twice...

        $finishQueryBuilderModifier = function ($queryBuilder) use ($orderby) {
            if ($orderby != ''):
                $queryBuilder->orderBy($orderby, 'ASC');
            endif;
        };
        
        $countQueryBuilderModifier = function ($queryBuilder) {
            $queryBuilder->select('COUNT(DISTINCT base.uuid) AS total_results') // ->orderBy(null) does not remove orderby
                // ->groupBy('base.term') // suggested by Postgres error // TODO: Would it be only needed for counting?            
                ->setMaxResults(1);
        };
        
        $adapter = new TwoModifiers($queryBuilder, $finishQueryBuilderModifier, $countQueryBuilderModifier);
        return new Pagerfanta($adapter);
    }

    public function getWhereManyToManyToManyPager($base_table, $base_id, $base_link_base_id, $base_link_table, $base_link_distant_id, $distant_link_base_id, $distant_link_table, $distant_link_distant_id, $distant_id, $distant_table, array $where)
    // PageFanta dependency should be isolated!
    {
        $queryBuilder = $this->getWhereManyToManyToManyQueryBuilder($base_table, $base_id, $base_link_base_id, $base_link_table, $base_link_distant_id, $distant_link_base_id, $distant_link_table, $distant_link_distant_id, $distant_id, $distant_table, $where);

        $countQueryBuilderModifier = function ($queryBuilder) {
            $queryBuilder->select('COUNT(DISTINCT distant.uuid) AS total_results')
                  ->setMaxResults(1);
        };
        
        $adapter = new DoctrineDbalAdapter($queryBuilder, $countQueryBuilderModifier);
        return new Pagerfanta($adapter);
    }

    public function getMoreManyToManyWherePager($more_table, $more_id, $base_more, $base_table, $base_id, $link_base_id, $link_table, $link_distant_id, $distant_id, $distant_table, array $where)
    // PageFanta dependency should be isolated!
    {
        $queryBuilder = $this->getMoreManyToManyWhereQueryBuilder(
            $more_table, $more_id, $base_more, $base_table, $base_id, 
            $link_base_id, $link_table, $link_distant_id, $distant_id, 
            $distant_table, $where);

        $countQueryBuilderModifier = function ($queryBuilder) {
            $queryBuilder->select('COUNT(DISTINCT base.uuid) AS total_results')
                  ->setMaxResults(1);
        };
        
        $adapter = new DoctrineDbalAdapter($queryBuilder, $countQueryBuilderModifier);
        return new Pagerfanta($adapter);
    }

    public function getUrlIndexPager($more_table, $more_id, $base_more, $base_table, $base_id, $link_base_id, $link_table, $link_distant_id, $distant_id, $distant_table, array $where)
    // PageFanta dependency should be isolated!
    {
        $queryBuilder = $this->getUrlIndexQueryBuilder(
            $more_table, $more_id, $base_more, $base_table, $base_id, 
            $link_base_id, $link_table, $link_distant_id, $distant_id, 
            $distant_table, $where);

        $finishQueryBuilderModifier = function ($queryBuilder) {
            $queryBuilder
                ->groupBy('more.uuid') // suggested by Postgres error // TODO: Would it be only needed for counting?
                ->addGroupBy('base.uuid') // suggested by Postgres error // TODO: Would it be only needed for counting?
                ->orderBy('count(base.uuid=taxo.owned_url_uuid)', 'ASC')
            ;
        };
        
        $countQueryBuilderModifier = function ($queryBuilder) { // TODO: a simplified query may improve performance!
            // $queryBuilder->select('COUNT(DISTINCT base.uuid) AS total_results, count(base.uuid=taxo.owned_url_uuid) AS taxocount')
            $queryBuilder->select('COUNT(DISTINCT base.uuid) AS total_results')
                  ->setMaxResults(1);
        };
        
        $adapter = new TwoModifiers($queryBuilder, $finishQueryBuilderModifier, $countQueryBuilderModifier);
        return new Pagerfanta($adapter);
    }
}
// TODO: use portable quoting strings from DBAL or DBO.
//^ - DBAL: http://www.doctrine-project.org/api/dbal/2.5/class-Doctrine.DBAL.Platforms.AbstractPlatform.html#_getIdentifierQuoteCharacter
//^   NOTE: Just because you CAN use quoted identifiers doesn't mean you SHOULD use them. In general, they end up causing way more problems than they solve.
//^   Search for "quote" "quoteIdentifier" "quoteSingleIdentifier" "quoteStringLiteral" "getStringLiteralQuoteCharacter" in this page
//^   https://www.google.ca/search?q=quote+quoteIdentifier+quoteSingleIdentifier+quoteStringLiteral+getStringLiteralQuoteCharacter+site:www.doctrine-project.org/api/dbal
//^   https://www.google.ca/search?q=AbstractPlatform+quote+quoteIdentifier+quoteSingleIdentifier+quoteStringLiteral+getStringLiteralQuoteCharacter+site:www.doctrine-project.org/api/dbal
//^   $conn->getDatabasePlatform()->...
// Quoting of identifiers is SQL-dialect dependent (and differs between identifiers and literal values)
//^ https://stackoverflow.com/questions/22459092/pdo-postgresql-quoted-identifiers-in-where
//^ Postgres manual
//^ https://www.postgresql.org/docs/current/static/sql-syntax-lexical.html
// Quoting of values seems more or less similar in main SQL dialects
//^ https://www.w3schools.com/sql/sql_insert.asp
//^ https://www.postgresql.org/docs/current/static/dml-insert.html
