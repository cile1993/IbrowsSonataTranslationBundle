<?php

namespace Ibrows\SonataTranslationBundle\Admin;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\DoctrineORMAdminBundle\Datagrid\ProxyQuery;
use Sonata\DoctrineORMAdminBundle\Filter\CallbackFilter;
use Sonata\DoctrineORMAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ORMTranslationAdmin extends TranslationAdmin
{
    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $domains = array();
        $domainsQueryResult = $this->em->createQueryBuilder()
            ->select('DISTINCT t.domain')->from('\Lexik\Bundle\TranslationBundle\Entity\File', 't')
            ->getQuery()
            ->getResult(Query::HYDRATE_ARRAY);

        array_walk_recursive(
            $domainsQueryResult,
            function ($domain) use (&$domains) {
                $domains[$domain] = $domain;
            }
        );
        ksort($domains);

        $filter
            ->add(
                'locale',
                CallbackFilter::class,
                array(
                    'callback'      => function (ProxyQuery $queryBuilder, $alias, $field, $options) {
                        /* @var $queryBuilder \Doctrine\ORM\QueryBuilder */
                        if (!isset($options['value']) || empty($options['value'])) {
                            return;
                        }
                        // use on to filter locales
                        $this->joinTranslations($queryBuilder, $alias, $options['value']);
                    },
                    'field_options' => array(
                        'choices'  => $this->formatLocales($this->managedLocales),
                        'required' => false,
                        'multiple' => true,
                        'expanded' => false,
                    ),
                    'field_type'    => ChoiceType::class,
                )
            )
            ->add(
                'show_non_translated_only',
                CallbackFilter::class,
                array(
                    'callback'      => function (ProxyQuery $queryBuilder, $alias, $field, $options) {
                        /* @var $queryBuilder \Doctrine\ORM\QueryBuilder */
                        if (!isset($options['value']) || empty($options['value']) || false === $options['value']) {
                            return;
                        }
                        $this->joinTranslations($queryBuilder, $alias);

                        foreach ($this->getEmptyFieldPrefixes() as $prefix) {
                            if (empty($prefix)) {
                                $queryBuilder->orWhere('translations.content LIKE :empty')->setParameter(
                                    'empty',
                                    ''
                                );
                                $queryBuilder->orWhere('translations.content IS NULL');
                                $queryBuilder->orWhere('translations.content LIKE o.key');
                                ;
                            } else {
                                $queryBuilder->orWhere('translations.content LIKE :content')->setParameter(
                                    'content',
                                    $prefix.'%'
                                );
                            }
                        }
                    },
                    'field_options' => array(
                        'required' => true,
                        'value'    => 1,
                    ),
                    'field_type'    => CheckboxType::class,
                    'show_filter' => true,
                )
            )
            ->add('key', ChoiceFilter::class, array(
                'show_filter' => true,
            ))
            ->add(
                'domain',
                ChoiceFilter::class,
                array(
                    'field_options' => array(
                        'choices'     => $domains,
                        'required'    => true,
                        'multiple'    => false,
                        'expanded'    => false,
                        'empty_data'  => 'all',
                    ),
                    'field_type'    => ChoiceType::class,
                )
            )
            ->add(
                'content',
                CallbackFilter::class,
                array(
                    'callback'   => function (ProxyQuery $queryBuilder, $alias, $field, $options) {
                        /* @var $queryBuilder \Doctrine\ORM\QueryBuilder */
                        if (!isset($options['value']) || empty($options['value'])) {
                            return;
                        }
                        $this->joinTranslations($queryBuilder, $alias);
                        $queryBuilder->andWhere('translations.content LIKE :content')->setParameter(
                            'content',
                            '%'.$options['value'].'%'
                        );
                    },
                    'field_type' => TextType::class,
                    'label'      => 'content',
                    'show_filter' => true,
                )
            );
    }

    /**
     * @param ProxyQuery $queryBuilder
     * @param String     $alias
     */
    private function joinTranslations(ProxyQuery $queryBuilder, $alias, array $locales = null)
    {
        $alreadyJoined = false;
        $joins = $queryBuilder->getDQLPart('join');
        if (array_key_exists($alias, $joins)) {
            $joins = $joins[$alias];
            foreach ($joins as $join) {
                if (strpos($join->__toString(), "$alias.translations ")) {
                    $alreadyJoined = true;
                }
            }
        }
        if (!$alreadyJoined) {
            /** @var QueryBuilder $queryBuilder */
            if ($locales) {
                $queryBuilder->leftJoin(sprintf('%s.translations', $alias), 'translations', 'WITH', 'translations.locale in (:locales)');
                $queryBuilder->setParameter('locales', $locales);
            } else {
                $queryBuilder->leftJoin(sprintf('%s.translations', $alias), 'translations');
            }
        }
    }

    /**
     * @return array
     */
    private function formatLocales(array $locales)
    {
        $formattedLocales = array();
        array_walk_recursive(
            $locales,
            function ($language) use (&$formattedLocales) {
                $formattedLocales[$language] = $language;
            }
        );

        return $formattedLocales;
    }
}
