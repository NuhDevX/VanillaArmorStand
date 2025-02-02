<?php

namespace ZhaDev\vas;

use ZhaDev\vas\entity\EntityArmorStand;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\EntityDataHelper;
use pocketmine\plugin\PluginBase;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use pocketmine\item\StringToItemParser;
use pocketmine\inventory\CreativeInventory;
use pocketmine\world\World;
use pocketmine\nbt\tag\CompoundTag;
use ZhaDev\vas\item\ArmorStandItemRegistry;
use pocketmine\data\bedrock\item\ItemTypeNames;
use pocketmine\data\bedrock\item\SavedItemData;

class Main extends PluginBase {
	
	public function onEnable(): void {
        
        EntityFactory::getInstance()->register(EntityArmorStand::class, function(World $world, CompoundTag $nbt) : EntityArmorStand{
			return new EntityArmorStand(EntityDataHelper::parseLocation($nbt, $world), $nbt);
		}, ["PMArmorStand"]);
        
		GlobalItemDataHandlers::getDeserializer()->map(ItemTypeNames::ARMOR_STAND, fn() => clone ArmorStandItemRegistry::ARMOR_STAND());
        GlobalItemDataHandlers::getSerializer()->map(ArmorStandItemRegistry::ARMOR_STAND(), fn() => new SavedItemData(ItemTypeNames::ARMOR_STAND));
        StringToItemParser::getInstance()->register(ItemTypeNames::ARMOR_STAND, fn() => clone ArmorStandItemRegistry::ARMOR_STAND());
        CreativeInventory::getInstance()->add(ArmorStandItemRegistry::ARMOR_STAND());
    }
    
}
