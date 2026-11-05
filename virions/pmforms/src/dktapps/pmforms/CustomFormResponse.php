<?php

/*
 * This file is part of pmforms.
 * Copyright (C) 2018-2025 Dylan K. Taylor <https://github.com/dktapps-pm-pl/pmforms>
 *
 * pmforms is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace dktapps\pmforms;

use function array_key_exists;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * @phpstan-type ResponseData array<string, mixed>
 */
class CustomFormResponse{
	/**
	 * @var mixed[]
	 * @phpstan-var ResponseData
	 */
	private $data;

	/**
	 * @param mixed[] $data
	 * @phpstan-param ResponseData $data
	 */
	public function __construct(array $data){
		$this->data = $data;
	}

	public function getInt(string $name) : int{
		$value = $this->get($name);
		if(!is_int($value)){
			throw new \InvalidArgumentException("Value \"$name\" is not an integer");
		}
		return $value;
	}

	public function getString(string $name) : string{
		$value = $this->get($name);
		if(!is_string($value)){
			throw new \InvalidArgumentException("Value \"$name\" is not a string");
		}
		return $value;
	}

	public function getFloat(string $name) : float{
		$value = $this->get($name);
		if(!is_float($value) && !is_int($value)){
			throw new \InvalidArgumentException("Value \"$name\" is not a number");
		}
		return (float) $value;
	}

	public function getBool(string $name) : bool{
		$value = $this->get($name);
		if(!is_bool($value)){
			throw new \InvalidArgumentException("Value \"$name\" is not a boolean");
		}
		return $value;
	}

	/**
	 * @return mixed[]
	 * @phpstan-return ResponseData
	 */
	public function getAll() : array{
		return $this->data;
	}

	private function checkExists(string $name) : void{
		if(!array_key_exists($name, $this->data)){
			throw new \InvalidArgumentException("Value \"$name\" not found");
		}
	}

	private function get(string $name) : mixed{
		$this->checkExists($name);
		return $this->data[$name];
	}
}
