<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Hostinger / hCDN WAF returns plain "Forbidden" on large POSTs with hundreds of
 * nested items[i][field] inputs. Front-end packs those into *_json; expand here.
 */
trait ExpandsCompactedFormArrays
{
    /**
     * @param  list<string>  $keys
     */
    protected function expandCompactedFormArrays(Request $request, array $keys = ['items', 'adjustments']): void
    {
        foreach ($keys as $key) {
            $jsonKey = $key.'_json';
            if (! $request->filled($jsonKey)) {
                continue;
            }

            $decoded = json_decode((string) $request->input($jsonKey), true);
            if (! is_array($decoded)) {
                $decoded = [];
            }

            $request->merge([
                $key => $this->nullifyEmptyStrings($decoded),
            ]);
            $request->request->remove($jsonKey);
        }
    }

    protected function nullifyEmptyStrings(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->nullifyEmptyStrings($v);
            }

            return $value;
        }

        return $value === '' ? null : $value;
    }
}
