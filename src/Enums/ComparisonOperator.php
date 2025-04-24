<?php

namespace Fereydooni\LaravelElastoquent\Enums;

enum ComparisonOperator: string
{
    case EQUAL = '=';
    case NOT_EQUAL = '!=';
    case GREATER_THAN = '>';
    case GREATER_THAN_OR_EQUAL = '>=';
    case LESS_THAN = '<';
    case LESS_THAN_OR_EQUAL = '<=';
    case LIKE = 'like';
    case NOT_LIKE = 'not like';
    case IN = 'in';
    case NOT_IN = 'not in';
    case BETWEEN = 'between';
    case NOT_BETWEEN = 'not between';
    case IS_NULL = 'is null';
    case IS_NOT_NULL = 'is not null';
} 