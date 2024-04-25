<?php

namespace App\Service;

class RewriteRule
{
    private string $from;

    private array|bool $fromComponents;

    private string $to;

    private bool $hasQueryString = false;

    private bool $isValid = false;

    public function __construct(string $from, string $to)
    {
        $this->setFrom($from);
        $this->setTo($to);
    }

    public function getFrom() : string
    {
        return $this->from;
    }

    public function setFrom(string $from): void
    {
        $this->setIsValid($from);
        $this->setFromQuery($from);
        $this->from = $this->escapePath($this->fromComponents['path']);
    }

    public function getTo() : string
    {
        return $this->to;
    }

    public function setTo(string $to): void
    {
        $this->setIsValid($to);
        $this->to = $to;
    }

    public function isHasQueryString(): bool
    {
        return $this->hasQueryString;
    }

    public function setHasQueryString(bool $hasQueryString): void
    {
        $this->hasQueryString = $hasQueryString;
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function setIsValid(string $url): void
    {
        $url = str_replace(['ä','ö','ü'], ['ae','oe','ue'], $url);
        $this->isValid = filter_var($url, FILTER_VALIDATE_URL);
    }

    public function getFromQuery(): string
    {
        return $this->fromComponents['query'];
    }

    public function setFromQuery(string $fromQuery): void
    {
        $this->fromComponents = parse_url($fromQuery);

        if (empty($this->fromComponents['query'])) {
            $this->setHasQueryString(false);

            if (str_starts_with($this->fromComponents['path'], '/')) {
                $this->fromComponents['path'] = substr($this->fromComponents['path'], 1);
            }
        } else {
            $this->setHasQueryString(true);

            if (str_ends_with($this->fromComponents['query'], '/')) {
                $this->fromComponents['query'] .= '?';
            }
        }
    }

    private function escapePath(string $path): string
    {
        $specialChars = array('\\', '$', '^', '[', ']', '{', '}', '(', ')', '|', '.', '*');

        foreach ($specialChars as $char) {
            $path = str_replace($char, '\\' . $char, $path);
        }
        if (str_ends_with($path, '/')) {
            $path .= '?';
        }

        return $path;
    }
}