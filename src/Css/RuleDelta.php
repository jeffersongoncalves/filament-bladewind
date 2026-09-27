<?php

declare(strict_types=1);

namespace JeffersonGoncalves\Filament\BladeWind\Css;

use Daikazu\BladeWind\Pages\IndexedRule;
use Daikazu\BladeWind\Pages\UtilityRuleIndex;
use Daikazu\BladeWind\Stylesheets\StylesheetIndex;

/**
 * The CSS a Livewire update needs on top of what its page already loaded.
 *
 * A page file carries the rules of the page's class set (the covered tokens). An update can render
 * HTML the page never had (an action modal, a form, a notification): its tokens that are not
 * covered bring rules the page lacks. Appending just those would reorder the cascade: a streamed
 * rule lands after every page rule, although in the stylesheet it may sit before one that must
 * still win on the same element. So the delta also re-emits every rule of the fragment's tokens
 * (and every unconditional rule) that comes after the first new one, in stylesheet order.
 *
 * That is enough because a new rule can only match elements carrying one of the new tokens, i.e.
 * elements of the fragment, and every rule that can compete with it on such an element is indexed
 * under one of the fragment's tokens or is unconditional.
 */
final class RuleDelta
{
    private ?string $hash = null;

    private ?UtilityRuleIndex $index = null;

    public function __construct(
        private StylesheetIndex $stylesheets,
        private FilamentDriver $driver,
    ) {}

    /**
     * @param  list<string>  $covered  the tokens the page (and earlier deltas) already carry
     * @param  list<string>  $fragment  the tokens the update's HTML can produce
     */
    public function css(array $covered, array $fragment): string
    {
        $index = $this->index();

        if ($index === null) {
            return '';
        }

        return self::build($index, $covered, $fragment);
    }

    /**
     * @param  list<string>  $covered
     * @param  list<string>  $fragment
     */
    public static function build(UtilityRuleIndex $index, array $covered, array $fragment): string
    {
        /** @var array<int, true> $delivered */
        $delivered = [];

        foreach ($index->unconditional() as $rule) {
            $delivered[$rule->position] = true;
        }

        foreach ($covered as $token) {
            foreach ($index->rulesFor($token) as $rule) {
                $delivered[$rule->position] = true;
            }
        }

        /** @var array<int, IndexedRule> $candidates */
        $candidates = [];
        $first = null;

        foreach ($fragment as $token) {
            foreach ($index->rulesFor($token) as $rule) {
                $candidates[$rule->position] = $rule;

                if (! isset($delivered[$rule->position]) && ($first === null || $rule->position < $first)) {
                    $first = $rule->position;
                }
            }
        }

        if ($first === null) {
            return '';
        }

        foreach ($index->unconditional() as $rule) {
            if (! $rule->statement) {
                $candidates[$rule->position] = $rule;
            }
        }

        $rules = array_filter($candidates, static fn (IndexedRule $rule): bool => $rule->position >= $first);
        ksort($rules);

        return self::emit($rules);
    }

    /**
     * $rules re-emitted under their wrapper chains, merging back-to-back rules that shared one
     * block in the stylesheet (the same reading as BladeWind's PageCssBuilder).
     *
     * @param  array<int, IndexedRule>  $rules  keyed and sorted by stylesheet position
     */
    private static function emit(array $rules): string
    {
        $css = '';
        /** @var list<string> $open */
        $open = [];
        $expected = null;

        foreach ($rules as $rule) {
            if ($rule->wrappers !== $open || $rule->position !== $expected) {
                $css .= str_repeat('}', count($open));
                $css .= $rule->wrappers === [] ? '' : implode('{', $rule->wrappers).'{';
                $open = $rule->wrappers;
            }

            $text = $rule->css();
            $css .= $text;
            $expected = $rule->position + strlen($text);
        }

        return $css.str_repeat('}', count($open));
    }

    /**
     * The pooled rules of the configured panel theme, indexed once per build (per process).
     */
    private function index(): ?UtilityRuleIndex
    {
        foreach ($this->stylesheets->stylesheets() as $stylesheet) {
            if (! $stylesheet->found || $stylesheet->hash === null) {
                continue;
            }

            if ($this->index !== null && $this->hash === $stylesheet->hash) {
                return $this->index;
            }

            $css = $this->stylesheets->contents()[$stylesheet->name] ?? '';

            if (! $this->driver->detect($css)) {
                return null;
            }

            $split = $this->driver->split($css);
            $this->hash = $stylesheet->hash;

            return $this->index = UtilityRuleIndex::build($split->utilities, $this->driver->indexTokens(...));
        }

        return null;
    }
}
