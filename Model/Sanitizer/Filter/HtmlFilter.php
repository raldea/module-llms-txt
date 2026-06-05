<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Sanitizer\Filter;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\SanitizerFilterInterface;

/**
 * Strips HTML, normalizes whitespace, decodes entities, trims.
 *
 * Runs AFTER {@see CmsDirectiveFilter} and {@see PageBuilderFilter} so the
 * content it sees has already had its widgets resolved and its Page Builder
 * noise removed.
 *
 * @since 3.0.0
 */
class HtmlFilter implements SanitizerFilterInterface
{
    public function filter(string $content, OutputContextInterface $context): string
    {
        if ($content === '') {
            return '';
        }

        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace('/<style[^>]*>.*?<\/style>/is', ' ', $content) ?? $content;
        $content = strip_tags($content);
        $content = preg_replace(['/\{\{.*?\}\}/s', '/\[.*?\]/s'], ' ', $content) ?? $content;
        $content = str_replace(['#html-body', '&nbsp;'], ['', ' '], $content);

        return trim((string) preg_replace('/\s+/', ' ', $content));
    }
}
