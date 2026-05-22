<?php

namespace App\Http\Requests\Concerns;

use App\Support\TalentDateOfBirth;

trait NormalizesTalentDateOfBirth
{
    protected function normalizeTalentDateOfBirthInput(): void
    {
        $merged = TalentDateOfBirth::mergeAliases($this->all());
        $this->replace($merged);

        if ($this->has('age') && is_numeric($this->input('age'))) {
            $this->merge(['age' => (string) $this->input('age')]);
        }

        $payload = $this->all();
        TalentDateOfBirth::applyAgeFromDateOfBirth($payload);
        $this->replace($payload);
    }
}
