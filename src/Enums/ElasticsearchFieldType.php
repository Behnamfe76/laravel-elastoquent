<?php

namespace Fereydooni\LaravelElastoquent\Enums;

enum ElasticsearchFieldType: string
{
    case TEXT = 'text';
    case KEYWORD = 'keyword';
    case LONG = 'long';
    case INTEGER = 'integer';
    case SHORT = 'short';
    case BYTE = 'byte';
    case DOUBLE = 'double';
    case FLOAT = 'float';
    case HALF_FLOAT = 'half_float';
    case SCALED_FLOAT = 'scaled_float';
    case DATE = 'date';
    case DATE_NANOS = 'date_nanos';
    case BOOLEAN = 'boolean';
    case BINARY = 'binary';
    case OBJECT = 'object';
    case NESTED = 'nested';
    case GEO_POINT = 'geo_point';
    case GEO_SHAPE = 'geo_shape';
    case IP = 'ip';
    case COMPLETION = 'completion';
    case TOKEN_COUNT = 'token_count';
    case DENSE_VECTOR = 'dense_vector';
    case SPARSE_VECTOR = 'sparse_vector';
    case RANK_FEATURE = 'rank_feature';
    case RANK_FEATURES = 'rank_features';
    case FLATTENED = 'flattened';
    case SHAPE = 'shape';
    case SEARCH_AS_YOU_TYPE = 'search_as_you_type';
    case ALIAS = 'alias';
    case PERCOLATOR = 'percolator';
    case JOIN = 'join';
    case VERSION = 'version';
    case MURMUR3 = 'murmur3';
    case AGGREGATE_METRIC_DOUBLE = 'aggregate_metric_double';
    case HISTOGRAM = 'histogram';
} 