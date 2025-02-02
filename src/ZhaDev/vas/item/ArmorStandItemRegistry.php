<?php

declare(strict_types=1);

namespace ZhaDev\vas\item;

use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\utils\CloningRegistryTrait;

final class ArmorStandItemRegistry{
	use CloningRegistryTrait;

	protected static function register(string $name, Item $item) : void{
		self::_registryRegister($name, $item);
	}

	/**
	 * @return Item[]
	 * @phpstan-return array<string, Item>
	 */
	public static function getAll() : array{
		/** @var Item[] $result */
		$result = self::_registryGetAll();
		return $result;
	}

	protected static function setup() : void{
		self::register("armor_stand", new ArmorStandItem(new ItemIdentifier(ItemTypeIds::newId()), "Armor Stand"));
	}
}
