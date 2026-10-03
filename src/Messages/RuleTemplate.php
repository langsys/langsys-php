<?php

namespace Langsys\SDK\Messages;

/**
 * A rule object's sentence for one field (FRM-2, MSG-3): the template with
 * the field's label written in and its `{name}` markers intact, and the
 * params that fill them, read from the rule's public properties.
 */
final class RuleTemplate
{
    /**
     * The params a rule object's template is filled with: the public
     * property named by each of its `{name}` markers. Empty for a rule that
     * states no template.
     *
     * @param object $rule
     * @return array
     */
    public static function params($rule)
    {
        if (!$rule instanceof HasMessageTemplate) {
            return [];
        }

        return MessageTemplate::paramsFrom($rule, (string) $rule->template())['params'];
    }

    /**
     * What a listing reports for a rule object without a template, so every
     * binding says the same: [issue, fix].
     *
     * @param string $filledMessage
     * @return array{0: string, 1: string}
     */
    public static function missingTemplateProblem($filledMessage)
    {
        return [
            'is a rule object without a template, so it is listed from its filled message "' . $filledMessage . '"',
            'implement ' . HasMessageTemplate::class . ' and return its sentence from template(), with {name} markers for its public properties',
        ];
    }

    /**
     * @param object $rule
     * @param string $label The field's label, as the sentence shows it
     * @return array{template: string, params: array, missing: string[]}|null Null when the rule states no template
     */
    public static function forField($rule, $label)
    {
        if (!$rule instanceof HasMessageTemplate) {
            return null;
        }

        $template = str_replace(':attribute', (string) $label, (string) $rule->template());

        return ['template' => $template] + MessageTemplate::paramsFrom($rule, $template);
    }
}
