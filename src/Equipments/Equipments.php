<?php
namespace Equipments;

use Core\Core;
use Core\util\Util;
use GuiLibrary\GuiLibrary;
use HotbarSystemManager\HotbarSystemManager;
use Monster\mob\MonsterBase;
use Monster\mob\PersonBase;
use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\inventory\ChestInventory;
use pocketmine\inventory\DoubleChestInventory;
use pocketmine\inventory\EnderChestInventory;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\Position;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\tile\Tile;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Equipments extends PluginBase {
    private static $instance = null;
    //public $pre = "§c§l[ §f장비 §c]§r§c";
    public $pre = "§e•";

    public static function getInstance() {
        return self::$instance;
    }

    public function onLoad() {
        self::$instance = $this;
    }

    public function onEnable() {
        @mkdir($this->getDataFolder());
        $this->saveResource("Equipments.yml");
        $this->Equipments = new Config($this->getDataFolder() . "Equipments.yml", Config::YAML);
        $this->eqdata = $this->Equipments->getAll();
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->core = Core::getInstance();
        $this->util = new Util($this->core);
        $this->gui = GuiLibrary::getInstance();
        $this->hotbarSystem = HotbarSystemManager::getInstance();
    }

    public function onDisable() {
        unset($this->eqinv);
    }

    public function onCommand(CommandSender $sender, Command $command, $label, $args): bool {
        if ($command->getName() == "장비") {
            if (!$sender->isOp() || !isset($args[0]) || !isset($args[1]) || !is_numeric($args[1]))
                return true;
            $items = $this->getEquipment($args[0], $args[1], 1, 1);
            if ($items !== null) {
                foreach ($items as $key => $value) {
                    $sender->getInventory()->addItem($value);
                }
            }
            return true;
        }
    }

    public function getEquipment(string $itemName, int $amount = 1, int $code = 0, int $code_1 = 0) {
        $itemName = $this->ConvertName($itemName);
        if (!$this->isEquipment($itemName))
            return null;
        foreach ($this->eqdata as $type => $name) {
            if (isset($this->eqdata[$type][$itemName])) {
                if ($type == "반지" || $type == "펜던트") {
                    $id = explode(":", $this->eqdata[$type][$itemName])[1];
                    $dmg = explode(":", $this->eqdata[$type][$itemName])[2];
                } else {
                    $id = explode(":", $this->eqdata[$type][$itemName])[2];
                    $dmg = explode(":", $this->eqdata[$type][$itemName])[3];
                }
                $item = $this->NBT($id, $dmg, $itemName);
                $items = [];
                for ($i = $amount; $i > 0; $i--) {
                    $item_1 = $this->settingOption($item, $type, $code, $code_1);
                    $items[] = $item_1;
                }
                return $items;
            }
        }
    }

    public function ConvertName(string $itemName) {
        $itemName = str_replace(
                ["§0", "§1", "§2", "§3", "§4", "§5", "§6", "§7", "§8", "§9", "§a", "§b", "§c", "§d", "§e", "§f", "§l", "§o", "§r"],
                ["", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""],
                $itemName);
        return $itemName;
    }

    public function isEquipment(string $itemName) {
        $itemName = $this->ConvertName($itemName);
        foreach ($this->eqdata as $type => $name) {
            if (isset($this->eqdata[$type][$itemName])) {
                return true;
            }
        }
        return false;
    }

    public function NBT(int $id, int $dmg, string $itemName) {
        $item = Item::get($id, $dmg, 1);
        $item->setCustomName("§r§f{$itemName}");
        return $item;
    }

    //수정 (반환값 null)

    private function settingOption(Item $item, string $type, int $code = 0, int $code_1 = 0) {
        $info = $this->getEquipmentInfo($item->getName());
        if ($type == "반지" || $type == "펜던트") {
            $level = $info[0];
            $slot = mt_rand(0, 999);
            $option = [
                    "level" => $level,
                    "type" => "모든 직업군",
                    "type_1" => $type
            ];
            $spec = "";
            for ($i = 3; $i < count($info) - 1; $i++) {
                $spec .= "{$info[$i]}||";
            }
            $option["spec"] = $spec;
        } else {
            $level = $info[0];
            $stat = $info[1];
            if ($code == 0)
                $spec = floor($stat - $stat * 20 / 100);
            else
                $spec = floor($stat + $stat * (mt_rand(0, 40) - 20) / 100);
            if ($spec <= 0)
                $spec = 1;
            $slot = mt_rand(0, 999);

            if ($type == "모험가" || $type == "나이트") {
                $a = "ATK";
                $type_1 = "검";
            } elseif ($type == "아처") {
                $a = "ATK";
                $type_1 = "활";
            } elseif ($type == "위자드" || $type == "프리스트") {
                $a = "MATK";
                $type_1 = "스태프";
            } else {
                $a = "방어력";
                $type_1 = $type;
                $type = "모든 직업군";
            }
            $option = [
                    "level" => $level,
                    "type" => $type,
                    "a" => $a,
                    "spec" => $spec,
                    "type_1" => $type_1
            ];
        }
        if ($code_1 == 0) {
            $option["slot"] = 0;
        } else {
            if ($slot = 0)
                $option["slot"] = 2;
            elseif (0 < $slot && $slot <= 10)
                $option["slot"] = 1;
            else
                $option["slot"] = 0;
        }
        $option["upgrade"] = 1;
        $itemName = $item->getCustomName();
        $item->clearNamedTag();
        $item->setCustomName($itemName);
        if ($option["type_1"] == "반지" || $option["type_1"] == "펜던트") {
            $tag = new CompoundTag("Equipment", [
                    new StringTag("Name", (string) $this->ConvertName($item->getName())),
                    new StringTag("Job", (string) $type),
                    new StringTag("Type", (string) $option["type_1"]),
                    new StringTag("Spec", (string) $option["spec"]),
                    new IntTag("Upgrade", (int) $option["upgrade"]),
                    new IntTag("Slot", (int) $option["slot"]),
                    new StringTag("Slot_1", "-"),
                    new StringTag("Slot_2", "-")
            ]);
        } else {
            $tag = new CompoundTag("Equipment", [
                    new StringTag("Name", (string) $this->ConvertName($item->getName())),
                    new StringTag("Job", (string) $type),
                    new StringTag("Type", (string) $option["type_1"]),
                    new IntTag("Spec", (int) $option["spec"]),
                    new IntTag("Upgrade", (int) $option["upgrade"]),
                    new IntTag("Slot", (int) $option["slot"]),
                    new StringTag("Slot_1", "-"),
                    new StringTag("Slot_2", "-")
            ]);
        }
        $item->setCustomBlockData($tag);

        $lore = [];
        $lore[] = "§r§l§c▶ §r§f장비 등급: {$this->ConvertUpgrade($option["upgrade"])}";
        $lore[] = "§r§l§6▶ §r§f필요 레벨: {$option["level"]}";
        $lore[] = "§r§l§a▶ §r§f장비 직업군: {$option["type"]}";
        $lore[] = "\n§r§f┌─────────────┐";
        if (!isset($option["a"]) && $option["type"] == "모든 직업군") {
            $spec = explode("||", $option["spec"]);
            for ($i = 0; $i < count($spec); $i++) {
                if ($i == count($spec) - 1)
                    break;
                else
                    $lore[] = " §r§l§6▶ §r§f{$spec[$i]}";
            }
        } else {
            $lore[] = " §r§l§6▶ §r§f{$option["a"]}: {$option["spec"]}";
        }
        if ($option["slot"] !== 0) {
            for ($i = 0; $i < $option["slot"]; $i++) {
                $lore[] = " §r§l§d▶ §r§f보석: 비어있음";
            }
        }
        $lore[] = "§r§f└─────────────┘\n";
        $item->setLore($lore);
        unset($option);
        if ($item instanceof Durable)
            $item->setUnbreakable(true);
        return $item;
    }

    public function getEquipmentInfo(string $itemName) {
        $itemName = $this->ConvertName($itemName);
        if (!$this->isEquipment($itemName))
            return null;
        else {
            foreach ($this->eqdata as $type => $name) {
                if (isset($this->eqdata[$type][$itemName])) {
                    $arr = explode(":", $this->eqdata[$type][$itemName]);
                    return $arr;
                    //반지, 펜던트의 경우,
                    # $arr[0] == 레벨제한
                    # $arr[1] == 아이템 ID
                    # $arr[2] == 아이템 Damage
                    # $arr[3 ~ Max-1] == 효과
                    # $arr[Max] == 가격

                    //그 외의 경우,
                    # $arr[0] == 레벨제한
                    # $arr[1] == 피지컬
                    # $arr[2] == 아이템 ID
                    # $arr[3] == 아이템 Damage
                    # $arr[4] == 장비 가격
                }
            }
        }
    }

    public function ConvertUpgrade(int $int) {
        switch ($int) {
            case 1:
                return 1;

            case 2:
                return 2;

            case 3:
                return 3;

            case 4:
                return 4;

            case 5:
                return "D";

            case 6:
                return "C";

            case 7:
                return "B";

            case 8:
                return "A";

            case 9:
                return "S";

            case 10:
                return "SS";
        }
    }

    public function getWeapon(Player $player) {
        $item = $player->getInventory()->getIteminHand();
        $item_1 = $player->getEnderChestInventory()->getItem(0);
        if ($item->getName() == $item_1->getName()) {
            if ($this->isEquipment($this->ConvertName($item->getName()))) {
                if ($this->getEquipmentType($this->ConvertName($item->getName())) == "모험가" || $this->getEquipmentType($this->ConvertName($item->getName())) == "나이트") {
                    return "검";
                } elseif ($this->getEquipmentType($this->ConvertName($item->getName())) == "아처") {
                    return "활";
                } elseif ($this->getEquipmentType($this->ConvertName($item->getName())) == "위자드" || $this->getEquipmentType($this->ConvertName($item->getName())) == "프리스트") {
                    return "스태프";
                } else {
                    return null;
                }
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    // $code_1 == 0 : 슬롯 판단 안함, $code_1 == 1 : 슬롯 랜덤 돌림, $code == 0 : 장비 피지컬 고정(기본값-20%), $code == 1 : 장비 피지컬 랜점
    // 수정 -> 아이템 배열형태로만 반환 ( [아이템, 아이템, 아이템, ...])
    /*ex:
      $item = $this->getEquipment("몽둥이", 4);
      foreach($item as $key => $value){
        $player->getInventory()->addItem($item);
      }
      => 몽둥이 4개 지급
      */

    public function getEquipmentType(string $itemName) {
        $itemName = $this->ConvertName($itemName);
        if (!$this->isEquipment($itemName))
            return null;
        else {
            foreach ($this->eqdata as $type => $name) {
                if (isset($this->eqdata[$type][$itemName])) {
                    return (string) $type;
                }
            }
        }
    }

    public function settingRing(string $name, Item $item) {
        $itemName = $this->ConvertName($item->getCustomName());
        $this->util->setATK($name, 0, "EquipmentRing");
        $this->util->setDEF($name, 0, "EquipmentRing");
        $this->util->setMATK($name, 0, "EquipmentRing");
        $this->util->setMDEF($name, 0, "EquipmentRing");
        $this->util->setMaxHp($name, 0, "EquipmentRing");
        $this->util->setMaxMp($name, 0, "EquipmentRing");
        $this->util->setCritical($name, 0, "EquipmentRing");
        $this->util->setAutoHealHp($name, 0, "EquipmentRing");
        $this->util->setAutoHealMp($name, 0, "EquipmentRing");
        $this->util->setHitHealHp($name, 0, "EquipmentRing");
        $this->util->setHitHealMp($name, 0, "EquipmentRing");
        $this->util->setATKPer($name, 0, "EquipmentRing");
        $this->util->setDEFPer($name, 0, "EquipmentRing");
        $this->util->setMATKPer($name, 0, "EquipmentRing");
        $this->util->setMDEFPer($name, 0, "EquipmentRing");
        $this->util->setMaxHpPer($name, 0, "EquipmentRing");
        $this->util->setMaxMpPer($name, 0, "EquipmentRing");
        $this->util->setCriticalPer($name, 0, "EquipmentRing");
        $this->util->setHitHealHpPer($name, 0, "EquipmentRing");
        $this->util->setHitHealMpPer($name, 0, "EquipmentRing");
        if (!isset($this->eqdata["반지"][$itemName]))
            return false;
        $spec = explode("||", $item->getCustomBlockData()->getString("Spec"));
        for ($i = 0; $i < count($spec) - 1; $i++) {
            if (mb_stripos($spec[$i], "HP 자연회복") !== false) {
                $this->util->addAutoHealHp($name, (int) explode("%", explode("HP 자연회복 ", $spec[$i])[1])[0], "EquipmentRing");
                continue;
            }

            if (mb_stripos($spec[$i], "MP 자연회복") !== false) {
                $this->util->addAutoHealMp($name, (int) explode("%", explode("MP 자연회복 ", $spec[$i])[1])[0], "EquipmentRing");
                continue;
            }

            if (mb_stripos($spec[$i], "HP 최대량") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addMaxHpPer($name, (int) explode("%", explode("HP 최대량 ", $spec[$i])[1])[0], "EquipmentRing");
                } else {
                    $this->util->addMaxHp($name, (int) explode("HP 최대량 ", $spec[$i])[1], "EquipmentRing");
                }
                continue;
            }

            if (mb_stripos($spec[$i], "MP 최대량") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addMaxMpPer($name, (int) explode("%", explode("MP 최대량 ", $spec[$i])[1])[0], "EquipmentRing");
                } else {
                    $this->util->addMaxMp($name, (int) explode("MP 최대량 ", $spec[$i])[1], "EquipmentRing");
                }
                continue;
            }

            if (mb_stripos($spec[$i], "공격시 HP 회복") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addHitHealHpPer($name, (int) explode("%", explode("공격시 HP 회복 ", $spec[$i])[1])[0], "EquipmentRing");
                } else {
                    $this->util->addHitHealHp($name, (int) explode("공격시 HP 회복 ", $spec[$i])[1], "EquipmentRing");
                }
                continue;
            }

            if (mb_stripos($spec[$i], "공격시 MP 회복") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addHitHealMpPer($name, (int) explode("%", explode("공격시 MP 회복 ", $spec[$i])[1])[0], "EquipmentRing");
                } else {
                    $this->util->addHitHealMp($name, (int) explode("공격시 MP 회복 ", $spec[$i])[1], "EquipmentRing");
                }
                continue;
            }

            if (mb_stripos($spec[$i], "크리티컬율") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addCriticalPer($name, (int) explode("%", explode("크리티컬율 ", $spec[$i])[1])[0], "EquipmentRing");
                } else {
                    $this->util->addCritical($name, (int) explode("크리티컬율 ", $spec[$i])[1], "EquipmentRing");
                }
                continue;
            }

            if (mb_stripos($spec[$i], "크리티컬 데미지") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addCDPer($name, (int) explode("%", explode("크리티컬 데미지 ", $spec[$i])[1])[0], "EquipmentRing");
                } else {
                    $this->util->addCD($name, (int) explode("크리티컬 데미지 ", $spec[$i])[1], "EquipmentRing");
                }
                continue;
            }

            if (mb_stripos($spec[$i], "MATK") === false && mb_stripos($spec[$i], "ATK") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addATKPer($name, (int) explode("%", explode("ATK ", $spec[$i])[1])[0], "EquipmentRing");
                } else {
                    $this->util->addATK($name, (int) explode("ATK ", $spec[$i])[1], "EquipmentRing");
                }
                continue;
            }

            if (mb_stripos($spec[$i], "MDEF") === false && mb_stripos($spec[$i], "DEF") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addDEFPer($name, (int) explode("%", explode("DEF ", $spec[$i])[1])[0], "EquipmentRing");
                } else {
                    $this->util->addDEF($name, (int) explode("DEF ", $spec[$i])[1], "EquipmentRing");
                }
                continue;
            }

            if (mb_stripos($spec[$i], "MATK") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addMATKPer($name, (int) explode("%", explode("MATK ", $spec[$i])[1])[0], "EquipmentRing");
                } else {
                    $this->util->addMATK($name, (int) explode("MATK ", $spec[$i])[1], "EquipmentRing");
                }
                continue;
            }

            if (mb_stripos($spec[$i], "MDEF") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addMDEFPer($name, (int) explode("%", explode("MDEF ", $spec[$i])[1])[0], "EquipmentRing");
                } else {
                    $this->util->addMDEF($name, (int) explode("MDEF ", $spec[$i])[1], "EquipmentRing");
                }
                continue;
            }
        }
    }

    public function settingPendant(string $name, Item $item) {
        $itemName = $this->ConvertName($item->getCustomName());
        $this->util->setATK($name, 0, "EquipmentPendant");
        $this->util->setDEF($name, 0, "EquipmentPendant");
        $this->util->setMATK($name, 0, "EquipmentPendant");
        $this->util->setMDEF($name, 0, "EquipmentPendant");
        $this->util->setMaxHp($name, 0, "EquipmentPendant");
        $this->util->setMaxMp($name, 0, "EquipmentPendant");
        $this->util->setCritical($name, 0, "EquipmentPendant");
        $this->util->setAutoHealHp($name, 0, "EquipmentPendant");
        $this->util->setAutoHealMp($name, 0, "EquipmentPendant");
        $this->util->setHitHealHp($name, 0, "EquipmentPendant");
        $this->util->setHitHealMp($name, 0, "EquipmentPendant");
        $this->util->setATKPer($name, 0, "EquipmentPendant");
        $this->util->setDEFPer($name, 0, "EquipmentPendant");
        $this->util->setMATKPer($name, 0, "EquipmentPendant");
        $this->util->setMDEFPer($name, 0, "EquipmentPendant");
        $this->util->setMaxHpPer($name, 0, "EquipmentPendant");
        $this->util->setMaxMpPer($name, 0, "EquipmentPendant");
        $this->util->setCriticalPer($name, 0, "EquipmentPendant");
        $this->util->setHitHealHpPer($name, 0, "EquipmentPendant");
        $this->util->setHitHealMpPer($name, 0, "EquipmentPendant");
        if (!isset($this->eqdata["펜던트"][$itemName]))
            return false;
        $spec = explode("||", $item->getCustomBlockData()->getString("Spec"));
        for ($i = 0; $i < count($spec) - 1; $i++) {

            if (mb_stripos($spec[$i], "HP 자연회복") !== false) {
                $this->util->addAutoHealHp($name, (int) explode("%", explode("HP 자연회복 ", $spec[$i])[1])[0], "EquipmentPendant");
                continue;
            }

            if (mb_stripos($spec[$i], "MP 자연회복") !== false) {
                $this->util->addAutoHealMp($name, (int) explode("%", explode("MP 자연회복 ", $spec[$i])[1])[0], "EquipmentPendant");
                continue;
            }

            if (mb_stripos($spec[$i], "HP 최대량") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addMaxHpPer($name, (int) explode("%", explode("HP 최대량 ", $spec[$i])[1])[0], "EquipmentPendant");
                } else {
                    $this->util->addMaxHp($name, (int) explode("HP 최대량 ", $spec[$i])[1], "EquipmentPendant");
                }
                continue;
            }

            if (mb_stripos($spec[$i], "MP 최대량") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addMaxMpPer($name, (int) explode("%", explode("MP 최대량 ", $spec[$i])[1])[0], "EquipmentPendant");
                } else {
                    $this->util->addMaxMp($name, (int) explode("MP 최대량 ", $spec[$i])[1], "EquipmentPendant");
                }
                continue;
            }

            if (mb_stripos($spec[$i], "공격시 HP 회복") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addHitHealHpPer($name, (int) explode("%", explode("공격시 HP 회복 ", $spec[$i])[1])[0], "EquipmentPendant");
                } else {
                    $this->util->addHitHealHp($name, (int) explode("공격시 HP 회복 ", $spec[$i])[1], "EquipmentPendant");
                }
                continue;
            }

            if (mb_stripos($spec[$i], "공격시 MP 회복") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addHitHealMpPer($name, (int) explode("%", explode("공격시 MP 회복 ", $spec[$i])[1])[0], "EquipmentPendant");
                } else {
                    $this->util->addHitHealMp($name, (int) explode("공격시 MP 회복 ", $spec[$i])[1], "EquipmentPendant");
                }
                continue;
            }

            if (mb_stripos($spec[$i], "크리티컬율") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addCriticalPer($name, (int) explode("%", explode("크리티컬율 ", $spec[$i])[1])[0], "EquipmentPendant");
                } else {
                    $this->util->addCritical($name, (int) explode("크리티컬율 ", $spec[$i])[1], "EquipmentPendant");
                }
                continue;
            }

            if (mb_stripos($spec[$i], "크리티컬 데미지") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addCDPer($name, (int) explode("%", explode("크리티컬 데미지 ", $spec[$i])[1])[0], "EquipmentPendant");
                } else {
                    $this->util->addCD($name, (int) explode("크리티컬 데미지 ", $spec[$i])[1], "EquipmentPendant");
                }
                continue;
            }

            if (mb_stripos($spec[$i], "MATK") === false && mb_stripos($spec[$i], "ATK") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addATKPer($name, (int) explode("%", explode("ATK ", $spec[$i])[1])[0], "EquipmentPendant");
                } else {
                    $this->util->addATK($name, (int) explode("ATK ", $spec[$i])[1], "EquipmentPendant");
                }
                continue;
            }

            if (mb_stripos($spec[$i], "MDEF") === false && mb_stripos($spec[$i], "DEF") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addDEFPer($name, (int) explode("%", explode("DEF ", $spec[$i])[1])[0], "EquipmentPendant");
                } else {
                    $this->util->addDEF($name, (int) explode("DEF ", $spec[$i])[1], "EquipmentPendant");
                }
                continue;
            }

            if (mb_stripos($spec[$i], "MATK") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addMATKPer($name, (int) explode("%", explode("MATK ", $spec[$i])[1])[0], "EquipmentPendant");
                } else {
                    $this->util->addMATK($name, (int) explode("MATK ", $spec[$i])[1], "EquipmentPendant");
                }
                continue;
            }

            if (mb_stripos($spec[$i], "MDEF") !== false) {
                if (mb_stripos($spec[$i], "%") !== false) {
                    $this->util->addMDEFPer($name, (int) explode("%", explode("MDEF ", $spec[$i])[1])[0], "EquipmentPendant");
                } else {
                    $this->util->addMDEF($name, (int) explode("MDEF ", $spec[$i])[1], "EquipmentPendant");
                }
                continue;
            }
        }
    }

    public function Equipment(Player $player) {
        $tile = $this->gui->addWindow($player, "장비 장착", 1);
        $this->eqinv[$player->getName()] = true;
        for ($i = 0; $i < 54; $i++) {
            if ($i == 19) $tile[0]->getInventory()->setItem(19, $player->getEnderChestInventory()->getItem(0), true);
            else if ($i == 12) $tile[0]->getInventory()->setItem(12, $player->getEnderChestInventory()->getItem(1), true);
            else if ($i == 21) $tile[0]->getInventory()->setItem(21, $player->getEnderChestInventory()->getItem(2), true);
            else if ($i == 30) $tile[0]->getInventory()->setItem(30, $player->getEnderChestInventory()->getItem(3), true);
            else if ($i == 39) $tile[0]->getInventory()->setItem(39, $player->getEnderChestInventory()->getItem(4), true);
            else if ($i == 32) $tile[0]->getInventory()->setItem(32, $player->getEnderChestInventory()->getItem(5), true);
            else if ($i == 23) $tile[0]->getInventory()->setItem(23, $player->getEnderChestInventory()->getItem(6), true);
            //else $tile[0]->getInventory()->setItem($i, $player->getEnderChestInventory()->getItem(26), true);
            else $tile[0]->getInventory()->setItem($i, Item::get(351, 2), true);
        }
        $tile[0]->send($player);
    }

    public function getATK(Player $player) {
        $name = $player->getName();
        $job = $this->util->getJob($name);
        $eqinventory = $player->getEnderChestInventory();
        if ($job == "모험가" || $job == "나이트" || $job == "아처") {
            if ($eqinventory->getItem(0)->getId() == 0) return 0;
            $tag = $eqinventory->getItem(0)->getCustomBlockData();
            $spec = $tag->getInt("Spec");
            return $spec;
        } else {
            return 0;
        }
    }

    public function getMATK(Player $player) {
        $name = $player->getName();
        $job = $this->util->getJob($name);
        $eqinventory = $player->getEnderChestInventory();
        if ($job == "위자드" or $job == "프리스트") {
            if ($eqinventory->getItem(0)->getId() == 0) return 0;
            $tag = $eqinventory->getItem(0)->getCustomBlockData();
            $spec = $tag->getInt("Spec");
            return $spec;
        } else {
            return 0;
        }
    }

    public function getDEF(Player $player) {
        $name = $player->getName();
        $eqinventory = $player->getEnderChestInventory();
        if ($eqinventory->getItem(1)->getId() == 0) {
            $spec1 = 0;
        } else {
            $tag = $eqinventory->getItem(1)->getCustomBlockData();
            $spec1 = $tag->getInt("Spec");
        }
        if ($eqinventory->getItem(2)->getId() == 0) {
            $spec2 = 0;
        } else {
            $tag = $eqinventory->getItem(2)->getCustomBlockData();
            $spec2 = $tag->getInt("Spec");
        }
        if ($eqinventory->getItem(3)->getId() == 0) {
            $spec3 = 0;
        } else {
            $tag = $eqinventory->getItem(3)->getCustomBlockData();
            $spec3 = $tag->getInt("Spec");
        }
        if ($eqinventory->getItem(4)->getId() == 0) {
            $spec4 = 0;
        } else {
            $tag = $eqinventory->getItem(4)->getCustomBlockData();
            $spec4 = $tag->getInt("Spec");
        }
        $DEF = $spec1 + $spec2 + $spec3 + $spec4;
        return $DEF;
    }

    public function getMDEF(Player $player) {
        $name = $player->getName();
        $eqinventory = $player->getEnderChestInventory();
        if ($eqinventory->getItem(1)->getId() == 0) {
            $spec1 = 0;
        } else {
            $tag = $eqinventory->getItem(1)->getCustomBlockData();
            $spec1 = $tag->getInt("Spec");
        }
        if ($eqinventory->getItem(2)->getId() == 0) {
            $spec2 = 0;
        } else {
            $tag = $eqinventory->getItem(2)->getCustomBlockData();
            $spec2 = $tag->getInt("Spec");
        }
        if ($eqinventory->getItem(3)->getId() == 0) {
            $spec3 = 0;
        } else {
            $tag = $eqinventory->getItem(3)->getCustomBlockData();
            $spec3 = $tag->getInt("Spec");
        }
        if ($eqinventory->getItem(4)->getId() == 0) {
            $spec4 = 0;
        } else {
            $tag = $eqinventory->getItem(4)->getCustomBlockData();
            $spec4 = $tag->getInt("Spec");
        }
        $MDEF = $spec1 + $spec2 + $spec3 + $spec4;
        return $MDEF;
    }

    public function getPrize($eqname) {
        foreach ($this->eqdata as $type => $name) {
            if (isset($this->eqdata[$type][$eqname])) {
                $data = explode(":", $this->eqdata[$type][$eqname]);
                $count = count($data);
                return $data[$count - 1];
            }
        }
        return false;
    }

    public function Bow(Player $player) {
        if (isset($this->cool[$player->getName()]["기본공격"]) && time() - $this->cool[$player->getName()]["기본공격"] < 1)
            return;
        $bow = $player->getInventory()->getIteminHand();
        $nbt = Entity::createBaseNBT(
                $player->add(0, $player->getEyeHeight(), 0),
                $player->getDirectionVector(),
                ($player->yaw > 180 ? 360 : 0) - $player->yaw,
                -$player->pitch
        );
        $diff = $player->getItemUseDuration();
        $p = $diff / 20;
        $force = min((($p ** 2) + $p * 2) / 3, 1) * 2;
        $entity = Entity::createEntity("Arrow", $player->getLevel(), $nbt, $player, $force == 2);
        if ($entity instanceof Projectile) {
            if ($entity instanceof Arrow) {
                $entity->setPickupMode(Arrow::PICKUP_CREATIVE);
            }
            $ev = new EntityShootBowEvent($player, $bow, $entity, $force);
            $player->getServer()->getPluginManager()->callEvent($ev);
            $entity = $ev->getProjectile();
            if ($ev->isCancelled()) {
                $entity->flagForDespawn();
                $player->getInventory()->sendContents($player);
            } else {
                $entity->setMotion($entity->getMotion()->multiply(3));
                if ($player->isSurvival()) {
                    //$bow->applyDamage(1);
                }
                if ($entity instanceof Projectile) {
                    $player->getServer()->getPluginManager()->callEvent($projectileEv = new ProjectileLaunchEvent($entity));
                    if ($projectileEv->isCancelled()) {
                        $ev->getProjectile()->flagForDespawn();
                    } else {
                        $ev->getProjectile()->spawnToAll();
                        $player->getLevel()->broadcastLevelSoundEvent($player, LevelSoundEventPacket::SOUND_BOW);
                        $this->cool[$player->getName()]["기본공격"] = time();
                    }
                } else {
                    $entity->spawnToAll();
                }
            }
        } else {
            $entity->spawnToAll();
        }
        return;
    }

    public function Staff(Player $player) {
        if (isset($this->cool[$player->getName()]["기본공격"]) and time() - $this->cool[$player->getName()]["기본공격"] < 2) return;
        $this->cool[$player->getName()]["기본공격"] = time();
        $x = -\sin($player->yaw / 180 * M_PI);
        $z = \cos($player->yaw / 180 * M_PI);
        $test = $player->getDirectionVector();
        $player->getLevel()->broadcastLevelSoundEvent($player->add(0, 0.62, 0), 184);
        for ($i = 0; $i <= 10; $i += 0.1) {
            $vec = new Vector3($player->x + $i * $test->x, $player->y + $player->getEyeHeight() + $i * $test->y, $player->z + $i * $test->z);
            if ($this->util->getJob($player->getName()) == "위자드")
                $player->getLevel()->addParticle(new DustParticle($vec, 255, 0, 1));
            else
                $player->getLevel()->addParticle(new DustParticle($vec, 54, 105, 207));
        }
        /*for($i = 0; $i <= 15; $i += 0.5){
          $vec = new Vector3($player->x + $i * $test->x, $player->y + $player->getEyeHeight() + $i * $test->y, $player->z + $i * $test->z);
          //$targeting = $player->level->getNearbyEntities(new AxisAlignedBB($vec->x - $target->getScale(), $vec->y, $vec->z - $target->getScale(), $vec->x + $target->getScale(), $vec->y + $target->getScale()*2, $vec->z + $target->getScale()));
          $target = $player->level->getNearbyEntities(new AxisAlignedBB($vec->x - 3, $vec->y - 3, $vec->z - 3, $vec->x + 3, $vec->y + 3, $vec->z + 3));
          foreach($targeting as $monster){
            $source = new EntityDamageByEntityEvent($player, $monster, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 0);
            $monster->attack($source);
            return true;
          }
        }*/
        foreach ($player->level->getNearbyEntities($player->boundingBox->expandedCopy(15, 15, 15), $player) as $target) {
            for ($i = 0; $i <= 10; $i += 0.5) {
                if ($player != $target && $target instanceof MonsterBase || $target instanceof PersonBase) {
                    $vec = new Vector3($player->x + $i * $test->x, $player->y + $player->getEyeHeight() + $i * $test->y, $player->z + $i * $test->z);
                    $targeting = $player->level->getNearbyEntities(new AxisAlignedBB($vec->x - $target->getScale(), $vec->y - $target->getScale(), $vec->z - $target->getScale(), $vec->x + $target->getScale(), $vec->y + $target->getScale() * 2, $vec->z + $target->getScale()));
                    foreach ($targeting as $monster) {
                        if ($monster->getId() == $target->getId()) {
                            $source = new EntityDamageByEntityEvent($player, $monster, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 0);
                            $monster->attack($source);
                            return true;
                        }
                    }
                    /*if($target->distance($vec) <= 2){
                        $source = new EntityDamageByEntityEvent($player, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 0);
                        $target->attack($source);
                        return true;
                    }*/
                }
            }
        }
    }

    public function CheckJob($job, $ev, $name) {
        $pre = $this->pre;
        if ($job == "나이트" or $job == "아처") {
            if ($this->util->getJob($name) !== $job) {
                Server::getInstance()->getPlayer($name)->sendMessage("{$pre} {$job}가 아니기에 사용할 수 없습니다.");
                $ev->setCancelled(true);
                return "빠꾸";
            }
        }
        if ($job == "위자드") {
            if ($this->util->getJob($name) !== "위자드" and $this->util->getJob($name) !== "프리스트") {
                Server::getInstance()->getPlayer($name)->sendMessage("{$pre} 위자드나 프리스트가 아니기에 사용할 수 없습니다.");
                $ev->setCancelled(true);
                return "빠꾸";
            }
        }
    }

    public function CheckLevel($Iname, $ev, $name) {
        $pre = $this->pre;
        if ($this->util->getLevel($name) < (explode(":", $this->eqdata[$this->util->getJob($name)][$Iname]))[0]) {
            $t = (explode(":", $this->eqdata[$this->util->getJob($name)][$Iname]))[0];
            Server::getInstance()->getPlayer($name)->sendMessage("{$pre} 레벨이 낮아 사용할 수 없습니다. 요구레벨 : Lv.{$t}");
            $ev->setCancelled(true);
            return "빠꾸";
        }
    }

    public function CheckLevel1($Iname, $ev, $name, $type) {
        $pre = $this->pre;
        if ($this->util->getLevel($name) < (explode(":", $this->eqdata[$type][$Iname]))[0]) {
            $t = (explode(":", $this->eqdata[$type][$Iname]))[0];
            Server::getInstance()->getPlayer($name)->sendMessage("{$pre} 레벨이 낮아 사용할 수 없습니다. 요구레벨 : Lv.{$t}");
            $ev->setCancelled(true);
            return "빠꾸";
        }
    }

    public function Swing(Player $player) {
        $pk = new AnimatePacket();
        $pk->action = 1;
        $pk->float = 0.0;
        $pk->entityRuntimeId = $player->getId();
        $player->dataPacket($pk);
    }

    public function StopBreak(Player $player, Block $block) {
        $pk = new LevelEventPacket();
        $pk->evid = 3601;
        $pk->data = 1;
        $pk->position = $block;
        //$pk->getBlockPosition((int)$block->getX(), (int)$block->getY(), (int)$block->getZ());
        $player->dataPacket($pk);
    }

    public function WearSound(Player $player) {
        $pk = new LevelSoundEventPacket();
        $pk->sound = mt_rand(94, 100);
        $pk->position = $player;
        $pk->extraData = -1;
        $pk->isBabyMob = false;
        $pk->disableRelativeVolume = true;
        $player->dataPacket($pk);
    }
}
