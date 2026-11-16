<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GroundedResponseFormatter
{
    public function format(array $result, array $context): array
    {
        $validator = Validator::make($result, ['sections' => ['required', 'array', 'min:1', 'max:8'], 'sections.*.title' => ['required', 'string', 'max:120'], 'sections.*.content' => ['required', 'string', 'max:4000'], 'source_keys' => ['present', 'array', 'max:30'], 'source_keys.*' => ['string', Rule::in(array_keys($context['sources']))]]);
        if ($validator->fails() || (count($context['sources']) > 0 && empty($result['source_keys']))) {
            throw new AiUnavailable('The AI response could not be verified against its sources. Please try again.');
        }
        $sections = [];
        foreach ($result['sections'] as $section) {
            $content = $section['content'];
            preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $content, $matches);
            if (array_diff($matches[1], array_keys($context['render_values']))) {
                throw new AiUnavailable('The AI response contained an unsupported fact. Please try again.');
            }
            $untemplated = preg_replace('/\{\{[a-zA-Z0-9_]+\}\}/', '', $content);
            if (preg_match('/[0-9{}]/', $untemplated.$section['title'])) {
                throw new AiUnavailable('The AI response contained an unverified number. Please try again.');
            }
            $content = preg_replace_callback('/\{\{([a-zA-Z0-9_]+)\}\}/', function (array $match) use ($context): string {
                $value = (string) $context['render_values'][$match[1]];

                return str_contains($match[1], 'value') && is_numeric($value) ? 'USD '.$value : $value;
            }, $content);
            $sections[] = ['title' => $section['title'], 'content' => $content];
        }

        return ['sections' => $sections, 'facts' => $context['facts'], 'sources' => array_values(array_intersect_key($context['sources'], array_flip($result['source_keys']))), 'generated_at' => now()->toIso8601String(), 'mode' => 'live', 'limitations' => $context['limitations']];
    }

    public function unsupported(): array
    {
        return ['sections' => [['title' => 'Available information', 'content' => 'I can answer questions about your accessible deals, closing dates, sales stages, customer rankings, and recorded loss reasons. Please ask a specific question in that scope. Historical changes and other users’ records are unavailable.']], 'facts' => (object) [], 'sources' => [], 'generated_at' => now()->toIso8601String(), 'mode' => 'live', 'limitations' => ['No database query was executed for this unsupported question.']];
    }
}
