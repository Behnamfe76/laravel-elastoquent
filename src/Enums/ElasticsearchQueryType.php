<?php

namespace Fereydooni\LaravelElastoquent\Enums;

enum ElasticsearchQueryType: string
{
    case MATCH = 'match';
    case MATCH_PHRASE = 'match_phrase';
    case MATCH_PHRASE_PREFIX = 'match_phrase_prefix';
    case MULTI_MATCH = 'multi_match';
    case QUERY_STRING = 'query_string';
    case SIMPLE_QUERY_STRING = 'simple_query_string';
    case TERM = 'term';
    case TERMS = 'terms';
    case RANGE = 'range';
    case EXISTS = 'exists';
    case PREFIX = 'prefix';
    case WILDCARD = 'wildcard';
    case REGEXP = 'regexp';
    case FUZZY = 'fuzzy';
    case TYPE = 'type';
    case IDS = 'ids';
    case BOOL = 'bool';
    case MUST = 'must';
    case MUST_NOT = 'must_not';
    case SHOULD = 'should';
    case FILTER = 'filter';
    case NESTED = 'nested';
    case HAS_CHILD = 'has_child';
    case HAS_PARENT = 'has_parent';
    case PARENT_ID = 'parent_id';
    case GEO_SHAPE = 'geo_shape';
    case GEO_BOUNDING_BOX = 'geo_bounding_box';
    case GEO_DISTANCE = 'geo_distance';
    case GEO_POLYGON = 'geo_polygon';
    case MORE_LIKE_THIS = 'more_like_this';
    case SCRIPT = 'script';
    case PERCOLATE = 'percolate';
} 