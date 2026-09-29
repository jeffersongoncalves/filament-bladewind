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
 * still win on the same element.
 *
 * So the delta is the stylesheet-ordered tail from the first new rule on: the new rules plus every
 * rule the page already has after that point. Any two rules then keep their relative order: both
 * before the tail (page only), or both re-emitted in order after the page. Re-emitting only the
 * fragment's rules is not enough: a re-emitted rule also matches elements outside the fragment
 * (the topbar's close button carries `fi-icon-btn` too) and would jump ahead of the page rules
 * that override it there.
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
        // Most updates render nothing the page did not already have: no need to touch the index.
        if (array_diff($fragment, $covered) === []) {
            return '';
        }

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
        /** @var array<int, IndexedRule> $delivered the page's rules (and earlier deltas'), by position */
        $delivered = [];

        foreach ($index->unconditional() as $rule) {
            if (! $rule->statement) {
                $delivered[$rule->position] = $rule;
            }
        }

        foreach ($covered as $token) {
            foreach ($index->rulesFor($token) as $rule) {
                $delivered[$rule->position] = $rule;
            }
        }

        /** @var array<int, IndexedRule> $new */
        $new = [];

        foreach ($fragment as $token) {
            foreach ($index->rulesFor($token) as $rule) {
                if (! isset($delivered[$rule->position])) {
                    $new[$rule->position] = $rule;
                }
            }
        }

        if ($new === []) {
            return '';
        }

        $first = min(array_keys($new));

        // Every rule the page already has from the first new one on is re-emitted, not only the
        // fragment's: a re-emitted rule applies to every element carrying its class, inside the
        // fragment or not, so each later page rule must follow it again to keep winning.
        $rules = $new + array_filter($delivered, static fn (IndexedRule $rule): bool => $rule->position > $first);
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

            $this->hash = $stylesheet->hash;

            return $this->index = Memo::get(
                'index',
                $stylesheet->hash,
                fn (): UtilityRuleIndex => UtilityRuleIndex::build($this->driver->split($css)->utilities, $this->driver->indexTokens(...)),
            );
        }

        return null;
    }
}
