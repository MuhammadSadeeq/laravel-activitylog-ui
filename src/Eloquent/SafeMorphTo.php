<?php

namespace MuhammadSadeeq\ActivitylogUi\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A MorphTo that tolerates recorded types whose class no longer exists.
 *
 * An activity log outlives the models it records. Delete or rename a model and
 * every row naming it still refers to a class that can no longer be
 * instantiated, which makes Eloquent's eager load throw "Class X not found" and
 * takes down any page containing one of those rows.
 *
 * Rows of an unresolvable type get a null relation instead, so the rest of the
 * activity — who, when, what changed — remains readable.
 */
class SafeMorphTo extends MorphTo
{
    /**
     * Set when the parent row's own recorded type cannot be resolved, so the
     * lazy path can answer null without going to the database.
     */
    protected bool $typeIsMissing = false;

    public function markTypeAsMissing(): static
    {
        $this->typeIsMissing = true;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Unresolvable types are removed from the dictionary and their models given
     * a null relation; everything else is handed to the parent implementation so
     * this does not have to track changes to Eloquent's eager-loading algorithm.
     */
    public function getEager()
    {
        foreach (array_keys($this->dictionary) as $type) {
            if (!MorphTypes::missing($type)) {
                continue;
            }

            $this->resolveTypeToNull($type);

            unset($this->dictionary[$type]);
        }

        return parent::getEager();
    }

    /**
     * {@inheritdoc}
     *
     * Avoids a guaranteed-empty query per row on the lazy path.
     */
    public function getResults()
    {
        if ($this->typeIsMissing) {
            return null;
        }

        return parent::getResults();
    }

    /**
     * Give every model recorded against a missing type a null relation.
     *
     * Without this they are simply left unmatched, and the first read of the
     * relation falls through to a lazy load that throws.
     */
    protected function resolveTypeToNull(string $type): void
    {
        foreach ($this->dictionary[$type] as $models) {
            foreach ($models as $model) {
                if ($model instanceof Model) {
                    $model->setRelation($this->relationName, null);
                }
            }
        }
    }
}
