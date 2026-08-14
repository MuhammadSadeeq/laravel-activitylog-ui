<?php

namespace MuhammadSadeeq\ActivitylogUi\Eloquent;

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
     * {@inheritdoc}
     */
    public function getEager()
    {
        foreach (array_keys($this->dictionary) as $type) {
            if (MorphTypes::missing($type)) {
                $this->markTypeAsUnresolvable($type);

                continue;
            }

            $this->matchToMorphParents($type, $this->getResultsByType($type));
        }

        return $this->models;
    }

    /**
     * Set the relation to null on every model recorded against a missing type.
     *
     * Without this the models are simply left unmatched, and the first read of
     * the relation falls through to a lazy load that throws.
     */
    protected function markTypeAsUnresolvable(string $type): void
    {
        foreach ($this->dictionary[$type] as $models) {
            foreach ($models as $model) {
                $model->setRelation($this->relationName, null);
            }
        }
    }
}
