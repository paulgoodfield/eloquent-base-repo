<?php

namespace PaulGoodfield\EloquentBaseRepo;

use PaulGoodfield\BaseRepoInterface\Contracts\BaseRepositoryInterface;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

abstract class BaseRepository implements BaseRepositoryInterface {

	/**
	 * Retrieve all records for model
	 * @param  array  $columns  Columns to retrieve
	 * @param  array  $order    Associative array of field/order
	 * @return Illuminate\Support\Collection
	 */
	public function all($columns = array('*'), $order = array())
	{
		$query = $this->model->select($columns);
		if (!empty($order))
		{
			foreach($order as $key => $val)
			{
				$query->orderBy($key, $val);
			}
		}
		return $this->convertCollection($query->get());
	}

	/**
	 * Retrieve a specific record
	 * @param  integer  $id       Id of record to retrieve
	 * @param  array    $columns  Columns to retrieve
	 * @return stdClass
	 */
	public function find($id, $columns = array('*'))
	{
		if ($this->usesSoftDeletes($this->model))
		{
			$model = $this->model->withTrashed();
		}
		else
		{
			$model = $this->model;
		}

		$model = $model->find($id, $columns);

		if (is_null($model))
		{
			return $model;
		}
		
		return $this->convertModel($model);
	}

	/**
	 * Retrieve a specific record
	 * @param  array    $fieldvals  Associative array of fields and values
	 * @param  array    $columns    Columns to retrieve
	 * @param  array    $order      Array of columns to order by
	 * @param  integer  $start      Record to start retrieving from
	 * @param  integer  $limit      How many records to retrieve
	 * @return Illuminate\Support\Collection
	 */
	public function findWhere($fieldvals = array(), $columns = array('*'), $order = array(), $start = 0, $limit = 10000)
	{
		$query = $this->model->select($columns)->take($limit);
		if ($start > 0)
		{
			$query->skip($start);
		}
		foreach($fieldvals as $fld => $val)
		{
			$query->where($fld, $val);
		}
		if (!empty($order))
		{
			foreach($order as $key => $val)
			{
				$query->orderBy($key, $val);
			}
		}

		if ($this->usesSoftDeletes($this->model))
		{
			$collection = $query->withTrashed()->get();
		}
		else
		{
			$collection = $query->get();
		}

		return $this->convertCollection($collection);
	}

	/**
	 * Determines if current model uses soft deletes
	 * @return boolean
	 */
	private function usesSoftDeletes($model)
	{
		$traits = class_uses($model);
		if (in_array('Illuminate\Database\Eloquent\SoftDeletes', $traits))
		{
			return true;
		}

		return false;
	}

	/**
	 * Create a record
	 * @param  array  $data  Associative array of data
	 * @return stdClass
	 */
	public function create(array $data)
	{
		$model = $this->model->create($data);
		return $this->convertModel($model);
	}

	/**
	 * Update a record. Return Model if successful or false if unsuccessful
	 * @param  integer  $id    Id of record to update
	 * @param  array    $data  Associative array of data
	 * @return boolean
	 */
	public function update($id, array $data)
	{
		// Find record
		$record = $this->model->findOrFail($id);

		// Set attributes from data on to model
		foreach ($data as $key => $val)
		{
			$record->setAttribute($key, $val);
		}

		// If no changes have been made, return true
		if ($record->isClean())
		{
			return true;
		}
		
		// Return result of update
		return $record->update($data);
	}

	/**
	 * Delete record
	 * @param  integer  $id  Id of record to delete
	 * @return integer       Number of deletes
	 */
	public function delete($id)
	{
		return $this->model->destroy($id);
	}

	/**
	 * Save a relationship for many-to-many relationships
	 * @param  int     $id          Id of record to save relationship for
	 * @param  string  $relation    Name of relationship to save
	 * @param  int     $relationId  Id of related record
	 * @param  array   $additional  Array of additional data to be saved in pivot table
	 * @return void
	 */
	public function attach($id, $relation, $relationId, $additional = [])
	{
		// Find record
		$record = $this->model->findOrFail($id);

		// Attach relationship
		$record->$relation()->attach($relationId, $additional);
	}

	/**
	 * Remove a relationship for many-to-many relationships
	 * @param  int     $id          Id of record to save relationship for
	 * @param  string  $relation    Name of relationship to save
	 * @param  int     $relationId  Id of related record
	 * @return void
	 */
	public function detach($id, $relation, $relationId)
	{
		// Find record
		$record = $this->model->findOrFail($id);

		// Detach relationship
		$record->$relation()->detach($relationId);
	}

	/**
	 * Convert Eloquent collection to Base collection
	 * @param  BaseCollection  $collection  Eloquent collection to convert
	 * @return Illuminate\Support\Collection
	 */
	public function convertCollection(BaseCollection $collection)
	{
		$arr = [];
		
		foreach ($collection as $item)
		{
			if (get_class($item) != 'stdClass')
			{
				$item = $this->convertModel($item);
			}
			$arr[] = $item;
		}

		return collect($arr);
	}

	/**
	 * Convert Eloquent model to stdClass object
	 * @param  mixed     $model Either null or instance of Model
	 * @return stdClass
	 */
	public function convertModel($model)
	{
		if (is_null($model))
		{
			return null;
		}

		$json   = $model->toJson();
		$return = json_decode($json);

		// Check if model has soft deletes
		if ($this->usesSoftDeletes($model))
		{
			$return->trashed = ($model->trashed()) ? true : false;
		}
		
		return $return;
	}
}