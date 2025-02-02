<?php

declare(strict_types=1);

namespace ZhaDev\vas\entity;

use pocketmine\world\Position;
use pocketmine\entity\projectile\Arrow;
use ZhaDev\vas\particle\ArmorStandDestroyParticle;
use ZhaDev\vas\sound\ArmorStandBreakSound;
use ZhaDev\vas\sound\ArmorStandHitSound;
use ZhaDev\vas\sound\ArmorStandFallSound;
use ZhaDev\vas\sound\ArmorStandPlaceSound;
use ZhaDev\vas\util\EquipmentSlot;
use ZhaDev\vas\equipment\ArmorStandEntityEquipment;
use ZhaDev\vas\item\ArmorStandItemRegistry;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\ArmorInventory;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\InventoryContentPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\MobArmorEquipmentPacket;
use pocketmine\player\Player;
use pocketmine\entity\Location;
use RuntimeException;
use function array_merge;
use function min;

class EntityArmorStand extends Living{

	public const NETWORK_ID = EntityIds::ARMOR_STAND;

	public const TAG_MAINHAND = "Mainhand";
	public const TAG_OFFHAND = "Offhand";
	public const TAG_POSE_INDEX = "PoseIndex";
	public const TAG_ARMOR = "Armor";

	/** @var ArmorStandEntityEquipment */
	protected $equipment;

	public const WIDTH = 0.5;
	public const HEIGHT = 1.975;

	protected const GRAVITY = 0.04;
    private $properties = [];
	
	private ?Item $item = null; // Nullable dan di-set null secara default

   private $propertyManager;
	protected $vibrateTimer = 0;
	private Inventory $inventory;

    protected Location $location;
	/**
	 * @return ArmorStandEntityEquipment
	 */
	public function getEquipment() : ArmorStandEntityEquipment{
		return $this->equipment;
    }
	
	public static function getNetworkTypeId() : string{
		return self::NETWORK_ID;
	}
	
	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(self::HEIGHT, self::WIDTH);
	}
	
	protected function getInitialGravity() : float {
		return self::GRAVITY;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(6);
        $this->setNoClientPredictions(true);

		parent::initEntity($nbt);

        $this->propertyManager = new EntityMetadataCollection($this);        
		$this->equipment = new ArmorStandEntityEquipment($this);

		if($nbt->getTag(self::TAG_ARMOR, ListTag::class)){
			$armors = $nbt->getListTag(self::TAG_ARMOR);

			/** @var CompoundTag $armor */
			foreach($armors as $armor){
				$slot = $armor->getByte("Slot", 0);

				$this->armorInventory->setItem($slot, Item::nbtDeserialize($armor));
			}
		}

		if($nbt->getTag(self::TAG_MAINHAND, CompoundTag::class)){
			$this->equipment->setItemInHand(Item::nbtDeserialize($nbt->getCompoundTag(self::TAG_MAINHAND)));
		}
		if($nbt->getTag(self::TAG_OFFHAND, CompoundTag::class)){
			$this->equipment->setOffhandItem(Item::nbtDeserialize($nbt->getCompoundTag(self::TAG_OFFHAND)));
		}

		$this->setPose(min($nbt->getInt(self::TAG_POSE_INDEX, 0), 12));
		$this->propertyManager->setString(EntityMetadataProperties::INTERACTIVE_TAG, "armorstand.change.pose");
	}

	public function setPose(int $pose) : void{
		$this->propertyManager->setInt(EntityMetadataProperties::ARMOR_STAND_POSE_INDEX, $pose);
	}

	public function getPose() : int{
		return $this->propertyManager->getInt(EntityMetadataProperties::ARMOR_STAND_POSE_INDEX);
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if($player->isSneaking()){
			$this->setPose(($this->getPose() + 1) % 13);
			return true;
		}
		if($this->getPosition()->isValid() && !$player->isSpectator()){
			$targetSlot = EquipmentSlot::MAINHAND;
			$isArmorSlot = false;
			if($this->item instanceof Armor){
				$targetSlot = $this->item->getArmorSlot();
				$isArmorSlot = true;
			}elseif($this->item->getTypeId() === ItemTypeIds::fromBlockTypeId(BlockTypeIds::MOB_HEAD) || $this->item->getTypeId() === ItemTypeIds::fromBlockTypeId(BlockTypeIds::PUMPKIN)){
				$targetSlot = $this->armorInventory->getHelmet();
				$isArmorSlot = true;
			}elseif($this->item->isNull()){
				$clickOffset = $clickPos->y - $this->y;
				if($clickOffset >= 0.1 && $clickOffset < 0.55 && !$this->armorInventory->getItem(ArmorInventory::SLOT_FEET)->isNull()){
					$targetSlot = $this->armorInventory->getBoots();
					$isArmorSlot = true;
				}elseif($clickOffset >= 0.9 && $clickOffset < 1.6 && !$this->armorInventory->getItem(ArmorInventory::SLOT_CHEST)->isNull()){
					$targetSlot = $this->armorInventory->getChestplate();
					$isArmorSlot = true;
				}elseif($clickOffset >= 0.4 && $clickOffset < 1.2 && !$this->armorInventory->getItem(ArmorInventory::SLOT_LEGS)->isNull()){
					$targetSlot = $this->armorInventory->getLeggings();
					$isArmorSlot = true;
				}elseif($clickOffset >= 1.6 && !$this->armorInventory->getItem(ArmorInventory::SLOT_HEAD)->isNull()){
					$targetSlot = $this->armorInventory->getHelmet();
					$isArmorSlot = true;
				}
			}
			$this->getWorld()->addSound($this->getPostion(), new ArmorStandPlaceSound);
			$this->tryChangeEquipment($player, $this->item, $targetSlot, $isArmorSlot);
			return true;
		}
		return false;
	}

	protected function tryChangeEquipment(Player $player, Item $targetItem, int $slot, bool $isArmorSlot = false) : void{
		$sourceItem = $isArmorSlot ? $this->armorInventory->getItem($slot) : $this->equipment->getItem($slot);

		if($isArmorSlot){
			$this->armorInventory->setItem($slot, (clone $targetItem)->setCount(1));
		}else{
			$this->equipment->setItem($slot, (clone $targetItem)->setCount(1));
		}

		if(!$targetItem->isNull() && $player->isSurvival()){
			$targetItem->pop();
		}

		if(!$targetItem->isNull() && $targetItem->equals($sourceItem)){
			$targetItem->setCount($targetItem->getCount() + $sourceItem->getCount());
		}else{
			$player->getInventory()->addItem($sourceItem);
		}

		$this->equipment->sendContents($player);
		$this->sendContents($player);
	}
	
	public function sendContents($target) : void{
		if($target instanceof Player){
			$target = [$target];
		}

		$pk = new MobArmorEquipmentPacket();
		$pk->actorRuntimeId = $this->armorInventory->getHolder()->getId();
		$pk->head = ItemStackWrapper::legacy($this->armorInventory->getHelmet());
		$pk->chest = ItemStackWrapper::legacy($this->armorInventory->getChestplate());
		$pk->legs = ItemStackWrapper::legacy($this->armorInventory->getLeggings());
		$pk->feet = ItemStackWrapper::legacy($this->armorInventory->getBoots());
		$pk->body = ItemStackWrapper::legacy(VanillaBlocks::AIR()->asItem());
		$pk->encode();

		foreach($target as $player){
			if($player === $this->armorInventory->getHolder()){
				$pk2 = new InventoryContentPacket();
				$pk2->windowId = $player->getCurrentWindow($this);
				$pk2->items = array_map([ItemStackWrapper::class, 'legacy'], $this->inventory->getContents(true));
				$player->getNetworkSession()->sendDataPacket($pk2);
			}else{
				$player->getNetworkSession()->sendDataPacket($pk);
			}
		}
	}

	protected function onHitGround() : ?float{
		$this->getWorld()->addSound($this->getPosition(), new ArmorStandFallSound());
        return null;
	}

	public function saveNBT() : CompoundTag {
		$nbt = parent::saveNBT();

		if($this->equipment instanceof ArmorStandEntityEquipment){
			$nbt->setTag($this->equipment->getItemInHand()->nbtSerialize(-1, self::TAG_MAINHAND), true);
			$nbt->setTag($this->equipment->getOffhandItem()->nbtSerialize(-1, self::TAG_OFFHAND), true);
		}

		if($this->armorInventory !== null){
			$armorTag = new ListTag(self::TAG_ARMOR, [], NBT::TAG_Compound);

			for($i = 0; $i < 4; $i++){
				$armorTag->push($this->armorInventory->getItem($i)->nbtSerialize($i));
			}

			$nbt->setTag($armorTag, true);
		}

		$nbt->setInt(self::TAG_POSE_INDEX, $this->getPose(), true);
	}

	public function getDrops() : array{
    //    return array_merge($this->equipment->getContents(), $this->armorInventory->getContents(), (array) ArmorStandItemRegistry::ARMOR_STAND());
        $drops = $this->armorInventory->getContents();
        if ($this->item !== null && !$this->item->isNull()) {
          $drops[] = $this->item;			
		}
		$drops[] = ArmorStandItemRegistry::ARMOR_STAND();
		return $drops;
    }
    
    public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if($source instanceof EntityDamageByChildEntityEvent && $source->getChild() instanceof Arrow){
			$this->kill();
		}
		
		if($source->getCause() === EntityDamageEvent::CAUSE_CONTACT){ // cactus
			$source->cancel();
		}

		if(!$source->isCancelled()){
			$this->propertyManager->setGenericFlag(EntityMetadataFlags::VIBRATING, true);
			$this->vibrateTimer += 30;
		}
	}

	protected function doHitAnimation() : void{
		$this->getWorld()->addSound($this->getPosition(), new ArmorStandHitSound());
	}

	public function startDeathAnimation() : void{
		$this->getWorld()->addSound($this->getPosition(), new ArmorStandBreakSound());
		$this->getWorld()->addParticle($this->getPosition(), new ArmorStandDestroyParticle());
	}

	protected function onDeathUpdate(int $tickDiff) : bool{
		return true;
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);

		$this->equipment->sendContents($player);
	}

	public function getName() : string{
		return "ArmorStand";
	}

	public function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->getGenericFlag(EntityMetadataFlags::VIBRATING) && $this->vibrateTimer-- <= 0){
			$this->propertyManager->setGenericFlag(EntityMetadataFlags::VIBRATING, false);
		}

		return $hasUpdate;
	}
    
    public function getDataFlag(int $propertyId, int $flagId) : bool{
		return (((int) $this->getPropertyValue($propertyId, -1)) & (1 << $flagId)) > 0;
	}

	public function getGenericFlag(int $flagId) : bool{
		return $this->getDataFlag($flagId >= 64 ? EntityMetadataProperties::FLAGS2 : EntityMetadataProperties::FLAGS, $flagId % 64);
    }
    
    public function getPropertyValue(int $key, int $type){
		if($type !== -1){
			$this->checkType($key, $type);
		}
		return isset($this->properties[$key]) ? $this->properties[$key][1] : null;
    }
    
    private function checkType(int $key, int $type) : void{
		if(isset($this->properties[$key]) and $this->properties[$key][0] !== $type){
			throw new RuntimeException("Expected type $type, but have " . $this->properties[$key][0]);
		}
    }
    
  protected function onDeath() : void {
    parent::onDeath();

    $world = $this->getWorld();
    if ($world === null) {
        return;
    }

    if ($this->armorInventory !== null) {
        foreach ($this->armorInventory->getContents() as $item) {
            if (!$item->isNull()) {
                $world->dropItem($this->getPosition(), $item);
            }
        }
    }

    if ($this->equipment !== null) {
        $mainhandItem = $this->equipment->getItemInHand();
        if (!$mainhandItem->isNull()) {
            $world->dropItem($this->getPosition(), $mainhandItem);
        }

        $offhandItem = $this->equipment->getOffhandItem();
        if (!$offhandItem->isNull()) {
            $world->dropItem($this->getPosition(), $offhandItem);
        }
      }
    }   
    
    public function getPosition() : Position{
	   	return $this->location->asPosition();
    }
}
