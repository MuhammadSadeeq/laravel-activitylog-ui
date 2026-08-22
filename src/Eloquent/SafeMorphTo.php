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
     * Resolves the related model on its OWN connection.
     *
     * Eloquent points a morph target at the parent's connection whenever the
     * target does not declare one of its own. That is a fair default when
     * everything shares a database, but the activity table does not have to:
     * pointing activitylog.activity_model at a model with its own $connection —
     * an audit database, a reporting replica — is a supported setup, and this
     * package resolves the table from that model. Inheriting the parent's
     * connection then sent "select * from users where id in (...)" to the audit
     * connection, which has no users table, and every listing failed.
     *
     * Leaving the model alone means it resolves the way it does everywhere else
     * in the application: its declared connection, or the application default.
     * An application whose models live somewhere their own configuration does
     * not name is already broken outside this package.
     */
    public function createModelByType($type)
    {
        $class = Model::getActualClassNameForMorph($type);

        return new $class;
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
