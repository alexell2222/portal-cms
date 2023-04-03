<?php

namespace App\Models\Traits;

use CodeIgniter\Entity\Entity;
use \Config\Services;

/**
 * @property class  $translationModelClass
 * @property class  $translationEntityClass
 * @property string $translationTable
 * @property string $translationForeignKey
 * @property array  $translationFields
 */

trait ModelTranslationTrait
{
    private $locale;

    /**
     * Get locale
     * 
     * @return string locale
     */
    private function locale(): string
    {
        return $this->locale ?? $this->currentLang();
    }

    /**
     * Get the current language, that user selected
     * 
     * @return string Current language
     */
    public function currentLang(): string
    {
        return Services::language()->getLocale();
    }

    /**
     * Set the locale to be used
     * 
     * @return void
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /** 
     * Fetches the row of database from original joined with translations table with primary key
     * matching $id, and locale matching current language
     * 
     * @param int The id of the entity in original table
     * 
     * @return Entity|null The resulting row of data, or null.
     */
    public function findTranslated($id): Entity|null
    {
        $translated = $this->select($this->joinFields())
            ->join($this->translationTable, $this->joinCondition())
            ->where($this->translationForeignKey, $id)->where('locale', $this->locale())->first();
        return $translated;
    }

    /**
     * Fetches all rows matching the current language, from original joined with translations table
     * 
     * @return array Array of entities
     */
    public function findAllTranslated(): array|null
    {
        $translated = $this->select($this->joinFields())
            ->join($this->translationTable, $this->joinCondition())
            ->where('locale', $this->locale())->findAll();
        return $translated;
    }

    /**
     * Update or insert, the original and the translation, for current language
     * 
     * @param string object Entity
     */
    public function saveTranslated(object $originalEntity)
    {
        //Save original entity fields
        try {
            $this->allowEmptyInserts()->save($originalEntity);
        } catch (\Exception $ex) {
        }

        //Update translation if found, or insert new translation 
        $translationModel = new $this->translationModelClass;
        if ($translationEntity = $translationModel->where('locale', $this->locale())->where($this->translationForeignKey, $originalEntity->id)->first()) {
            $translationEntity = $this->parseTranslated($translationEntity, $originalEntity);
            try {
                $translationModel->save($translationEntity);
            } catch (\Exception $ex) {
            }
        } else {
            $translationEntity = new $this->translationEntityClass;
            $translationEntity->{$this->translationForeignKey} = (isset($originalEntity->id)) ? $originalEntity->id : $this->getInsertID();
            $translationEntity->locale = $this->locale();
            $translationEntity = $this->parseTranslated($translationEntity, $originalEntity);
            $translationModel->save($translationEntity);
        }
    }

    /**
     * Calls the framework's delete()
     * 
     * @param int The id of the entity in original table
     * 
     * @return bool
     */
    public function deleteTranslated($id)
    {
        $this->delete($id);
    }

    /**
     * Fill translation entity fields, from original entity
     * 
     * @return Entity Translation entity with filled properties
     */
    protected function parseTranslated(Entity $translationEntity, Entity $originalEntity)
    {
        foreach ($this->translationFields as $field) {
            $translationEntity->$field = $originalEntity->$field;
        }
        return $translationEntity;
    }


    /** 
     * Get join condition for original and translations tables
     * */
    protected function joinCondition(): string
    {
        return $this->table . '.' . $this->primaryKey . ' = ' . $this->translationTable . '.' . $this->translationForeignKey;
    }

    /** 
     * Get the fields to select from joining original and translations tables
     * */
    protected function joinFields(): string
    {
        return $this->originalFields() . $this->translationFields();
    }

    /** 
     * Create string, to select all from original table
     * 
     * @return string All fields from original table, prefixed with original table name
     * */
    protected function originalFields(): string
    {
        return $this->table . '.*,';
    }

    /** 
     * Create string, to select translated fields from translations fields,
     * 
     * @return string The translation fields prefixed with translation table name
     * */
    protected function translationFields(): string
    {
        $selectFileds = '';
        foreach ($this->translationFields as $field) {
            $selectFileds .= $this->translationTable . '.' . $field . ',';
        }
        return $selectFileds;
    }
}
