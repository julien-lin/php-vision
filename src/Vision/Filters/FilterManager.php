<?php

namespace JulienLinard\Vision\Filters;

use JulienLinard\Vision\Exception\InvalidFilterException;

class FilterManager
{
    /** @var array<string, FilterInterface> */
    private array $filters = [];

    private ?EscapeFilter $escapeFilter = null;

    public function addFilter(FilterInterface $filter): void
    {
        $this->filters[$filter->getName()] = $filter;
        if ($filter instanceof EscapeFilter) {
            $this->escapeFilter = $filter;
        }
    }

    public function has(string $name): bool
    {
        return isset($this->filters[$name]);
    }

    /**
     * @throws InvalidFilterException
     */
    public function apply(string $filterExpression, mixed $value): mixed
    {
        $filterExpression = trim($filterExpression);
        $parts = explode(':', $filterExpression, 2);
        $filterName = trim($parts[0]);
        $params = isset($parts[1]) ? $this->parseParams($parts[1]) : [];

        if (!isset($this->filters[$filterName])) {
            throw new InvalidFilterException($filterName);
        }

        return $this->filters[$filterName]->apply($value, $params);
    }

    public function getEscapeFilter(): EscapeFilter
    {
        if ($this->escapeFilter === null) {
            $this->escapeFilter = new EscapeFilter();
            $this->addFilter($this->escapeFilter);
        }
        return $this->escapeFilter;
    }

    private function parseParams(string $paramsString): array
    {
        $params = [];
        $parts = explode(',', $paramsString);
        foreach ($parts as $part) {
            $part = trim($part, " '\"");
            $params[] = $part;
        }
        return $params;
    }
}
