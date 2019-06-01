<?php

namespace Equipments;

use Core\Core;
use Core\util\Util;
use muqsit\invmenu\inventories\DoubleChestInventory as DoubleInventory;
use pocketmine\block\Block;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\DoubleChestInventory;
use pocketmine\inventory\EnderChestInventory;
use pocketmine\inventory\PlayerCursorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\transaction\action\CreativeInventoryAction;
use pocketmine\inventory\transaction\action\DropItemAction;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\Player;
use pocketmine\Server;

class EventListener implements Listener {

    public function __construct(Equipments $plugin) {
        $this->plugin = $plugin;
        $this->core = Core::getInstance();
        $this->util = new Util($this->core);
    }

    public function onJoin(PlayerJoinEvent $ev) {
        $server = Server::getInstance();
        $name = strtolower($ev->getPlayer()->getName());
        if (!file_exists("{$server->getDataPath()}players/{$name}.dat")) {
            $enderchest = $ev->getPlayer()->getEnderChestInventory();
            $item = $this->plugin->NBT(383, 38, "막힌 칸");
            $enderchest->setItem(0, new Item(0, 0), true);
            $enderchest->setItem(1, new Item(0, 0), true);
            $enderchest->setItem(2, new Item(0, 0), true);
            $enderchest->setItem(3, new Item(0, 0), true);
            $enderchest->setItem(4, new Item(0, 0), true);
            $enderchest->setItem(5, new Item(0, 0), true);
            $enderchest->setItem(6, new Item(0, 0), true);
            $enderchest->setItem(26, $item, true);
            $item->setLore(["§r§5장비칸을 이용해 장착해주세요."]);
            $ev->getPlayer()->getArmorInventory()->setHelmet($item);
            $ev->getPlayer()->getArmorInventory()->setChestplate($item);
            $ev->getPlayer()->getArmorInventory()->setLeggings($item);
            $ev->getPlayer()->getArmorInventory()->setBoots($item);
        }
    }

    public function onTouch(PlayerInteractEvent $ev) {
        if ($ev->getBlock()->getId() == "130")
            $ev->setCancelled(true);
        if ($ev->getItem()->getId() == 269 or $ev->getItem()->getId() == 273 or $ev->getItem()->getId() == 277 or $ev->getItem()->getId() == 284 or $ev->getItem()->getId() == 290 or $ev->getItem()->getId() == 291 or $ev->getItem()->getId() == 292 or $ev->getItem()->getId() == 293 or $ev->getItem()->getId() == 294) {
            if (!$ev->getPlayer()->isOp()) $ev->setCancelled(true);
        }
        if ($this->plugin->isEquipment($ev->getItem()->getCustomName())) {
            $ev->setCancelled(true);
        }
        if ($this->plugin->getWeapon($ev->getPlayer()) == "검") {
            $ev->setCancelled(true);
            if (stripos($ev->getPlayer()->getInventory()->getIteminHand()->getCustomName(), "몽둥이") !== false) {
                return;
            }
            $this->plugin->Swing($ev->getPlayer());
            $ev->getPlayer()->getLevel()->broadcastLevelSoundEvent($ev->getPlayer()->add(0, 0.62, 0), 183);
        }
        if ($this->plugin->getWeapon($ev->getPlayer()) == "활") {
            $ev->setCancelled(true);
            $this->plugin->Bow($ev->getPlayer());
        }
        if ($this->plugin->getWeapon($ev->getPlayer()) == "스태프") {
            $ev->setCancelled(true);
            $this->plugin->Staff($ev->getPlayer());
        }
        if ($ev->getPlayer()->getInventory()->getIteminHand()->getId() == 383) {
            $ev->setCancelled(true);
        }
    }

    public function onHeld(PlayerItemHeldEvent $ev) {
        $player = $ev->getPlayer();
        $name = $player->getName();
        $eqname = str_replace("§r§f", "", $ev->getItem()->getName());
        if (0 <= $ev->getSlot() && $ev->getSlot() <= 4) {
            //$ev->setCancelled(true);
            return false;
        }
        if (isset($this->plugin->eqdata["모험가"][$eqname])) {
            $player->getEnderChestInventory()->setItem(0, $ev->getItem(), true);
        } elseif (isset($this->plugin->eqdata["나이트"][$eqname])) {
            if (($this->plugin->CheckJob("나이트", $ev, $name)) == "빠꾸") return;
            if (($this->plugin->CheckLevel($eqname, $ev, $name)) == "빠꾸") return;
            $player->getEnderChestInventory()->setItem(0, $ev->getItem(), true);
        } elseif (isset($this->plugin->eqdata["아처"][$eqname])) {
            if (($this->plugin->CheckJob("아처", $ev, $name)) == "빠꾸") return;
            if (($this->plugin->CheckLevel($eqname, $ev, $name)) == "빠꾸") return;
            $player->getEnderChestInventory()->setItem(0, $ev->getItem(), true);
        } elseif (isset($this->plugin->eqdata["위자드"][$eqname])) {
            if (($this->plugin->CheckJob("위자드", $ev, $name)) == "빠꾸") return;
            if (($this->plugin->CheckLevel($eqname, $ev, $name)) == "빠꾸") return;
            $player->getEnderChestInventory()->setItem(0, $ev->getItem(), true);
        } else {
            $player->getEnderChestInventory()->setItem(0, new Item(0, 0), true);
        }
    }

    public function onWear(InventoryTransactionEvent $ev) {
        foreach ($ev->getTransaction()->getActions() as $action) {
            if ($ev->getTransaction()->getSource() instanceof Player) {
                if ($action instanceof CreativeInventoryAction) return;
                if ($action instanceof DropItemAction) return;
                if ($action->getInventory() instanceof ArmorInventory) $ev->setCancelled(true);
                if ($action->getInventory() instanceof EnderChestInventory) $ev->setCancelled(true);
                $player = $ev->getTransaction()->getSource();
                $name = $player->getName();
                if ($action->getInventory() instanceof DoubleInventory and isset($this->plugin->eqinv[$name])) {
                    $pre = $this->plugin->pre;
                    $Sitem = "{$action->getSourceItem()->getId()}:{$action->getSourceItem()->getDamage()}";
                    $Titem = "{$action->getTargetItem()->getId()}:{$action->getTargetItem()->getDamage()}";
                    $eqname = str_replace("§r§f", "", $action->getTargetItem()->getName());
                    //if($Sitem == "383:38") $ev->setCancelled(true);
                    if ($Sitem == "351:2") $ev->setCancelled(true);

                    if ($action->getSlot() == 19) {//무기
                        $ev->setCancelled(true);
                    } else if ($action->getSlot() == 12) {//모자
                        if ($Titem == "0:0") {
                            $player->getArmorInventory()->setHelmet(new Item(383, 38));
                            $player->getEnderChestInventory()->setItem(1, new Item(0, 0));
                            $this->plugin->WearSound($player);
                            return;
                        }
                        if (isset($this->plugin->eqdata["모자"][$eqname])) {
                            if (($this->plugin->CheckLevel1($eqname, $ev, $name, "모자")) == "빠꾸") return;
                            $player->getArmorInventory()->setHelmet($action->getTargetItem());
                            $player->getEnderChestInventory()->setItem(1, $action->getTargetItem());
                            $this->plugin->WearSound($player);
                            return;
                        } else {
                            $ev->setCancelled(true);
                        }
                    } else if ($action->getSlot() == 21) {//상의
                        if ($Titem == "0:0") {
                            $player->getArmorInventory()->setChestPlate(new Item(383, 38));
                            $player->getEnderChestInventory()->setItem(2, new Item(0, 0));
                            $this->plugin->WearSound($player);
                            return;
                        }
                        if (isset($this->plugin->eqdata["상의"][$eqname])) {
                            if (($this->plugin->CheckLevel1($eqname, $ev, $name, "상의")) == "빠꾸") return;
                            $player->getArmorInventory()->setChestPlate($action->getTargetItem());
                            $player->getEnderChestInventory()->setItem(2, $action->getTargetItem());
                            $this->plugin->WearSound($player);
                            return;
                        } else {
                            $ev->setCancelled(true);
                        }
                    } else if ($action->getSlot() == 30) {//하의
                        if ($Titem == "0:0") {
                            $player->getArmorInventory()->setLeggings(new Item(383, 38));
                            $player->getEnderChestInventory()->setItem(3, new Item(0, 0));
                            $this->plugin->WearSound($player);
                            return;
                        }
                        if (isset($this->plugin->eqdata["하의"][$eqname])) {
                            if (($this->plugin->CheckLevel1($eqname, $ev, $name, "하의")) == "빠꾸") return;
                            $player->getArmorInventory()->setLeggings($action->getTargetItem());
                            $player->getEnderChestInventory()->setItem(3, $action->getTargetItem());
                            $this->plugin->WearSound($player);
                            return;
                        } else {
                            $ev->setCancelled(true);
                        }
                    } else if ($action->getSlot() == 39) {//신발
                        if ($Titem == "0:0") {
                            $player->getArmorInventory()->setBoots(new Item(383, 38));
                            $player->getEnderChestInventory()->setItem(4, new Item(0, 0));
                            $this->plugin->WearSound($player);
                            return;
                        }
                        if (isset($this->plugin->eqdata["신발"][$eqname])) {
                            if (($this->plugin->CheckLevel1($eqname, $ev, $name, "신발")) == "빠꾸") return;
                            $player->getArmorInventory()->setBoots($action->getTargetItem());
                            $player->getEnderChestInventory()->setItem(4, $action->getTargetItem());
                            $this->plugin->WearSound($player);
                            return;
                        } else {
                            $ev->setCancelled(true);
                        }
                    } else if ($action->getSlot() == 32) {//반지
                        if ($Titem == "0:0") {
                            $player->getEnderChestInventory()->setItem(5, new Item(0, 0));
                            $this->plugin->settingRing($player->getName(), $action->getTargetItem());
                            $this->plugin->WearSound($player);
                            //$this->plugin->setting_clear($player, 0);
                            return;
                        }
                        if (isset($this->plugin->eqdata["반지"][$eqname])) {
                            if (($this->plugin->CheckLevel1($eqname, $ev, $name, "반지")) == "빠꾸") return;
                            $player->getEnderChestInventory()->setItem(5, $action->getTargetItem());
                            $this->plugin->settingRing($player->getName(), $action->getTargetItem());
                            $this->plugin->WearSound($player);
                            //$this->plugin->setting_set($player, $action->getTargetItem(), 0);
                            return;
                        } else {
                            $ev->setCancelled(true);
                        }
                    } else if ($action->getSlot() == 23) {//펜던트
                        if ($Titem == "0:0") {
                            $player->getEnderChestInventory()->setItem(6, new Item(0, 0));
                            $this->plugin->settingPendant($player->getName(), $action->getTargetItem());
                            $this->plugin->WearSound($player);
                            //$this->plugin->setting_clear($player, 0);
                            return;
                        }
                        if (isset($this->plugin->eqdata["펜던트"][$eqname])) {
                            if (($this->plugin->CheckLevel1($eqname, $ev, $name, "펜던트")) == "빠꾸") return;
                            $player->getEnderChestInventory()->setItem(6, $action->getTargetItem());
                            $this->plugin->settingPendant($player->getName(), $action->getTargetItem());
                            $this->plugin->WearSound($player);
                            //$this->plugin->setting_set($player, $action->getTargetItem(), 0);
                            return;
                        } else {
                            $ev->setCancelled(true);
                        }
                    }
                }
            }
        }
    }

    public function onDamage(EntityDamageEvent $ev) {
        if ($ev->isCancelled() == true)
            return false;
        if ($ev instanceof EntityDamageByEntityEvent) {
            $player = $ev->getDamager();
            $target = $ev->getEntity();
            if ($player instanceof Player && $target instanceof Player) {// 플레이어 => 플레이어 가격
                if (isset($this->plugin->cool[$player->getName()]["기본공격"]) && time() - $this->plugin->cool[$player->getName()]["기본공격"] < 1)
                    $ev->setCancelled(true);
                $this->plugin->cool[$player->getName()]["기본공격"] = time();
            } elseif ($player instanceof Player && ($target instanceof MonsterBase || $target instanceof PersonBase)) {// 플레이어 => 몬스터 가격
                if (isset($this->plugin->cool[$player->getName()]["기본공격"]) && time() - $this->plugin->cool[$player->getName()]["기본공격"] < 1)
                    $ev->setCancelled(true);
                $this->plugin->cool[$player->getName()]["기본공격"] = time();
            }
        }
    }

    public function onPacketReceived(DataPacketReceiveEvent $ev) {
        $pk = $ev->getPacket();
        /*if(stripos($pk->getName(), "Move") === false && stripos($pk->getName(), "Batch") === false)
            $ev->getPlayer()->getServer()->broadcastMessage((string)$pk->getName());*/
        if ($pk instanceof LevelSoundEventPacket && $pk->sound == LevelSoundEventPacket::SOUND_ATTACK_NODAMAGE) {//일반 터치
            $this->plugin->Swing($ev->getPlayer());
            if (stripos($ev->getPlayer()->getInventory()->getIteminHand()->getCustomName(), "몽둥이") !== false) {
                return;
            }
            if ($this->plugin->getWeapon($ev->getPlayer()) == "검") {
                $ev->getPlayer()->getLevel()->broadcastLevelSoundEvent($ev->getPlayer()->add(0, 0.62, 0), 183);
            }
            if ($this->plugin->getWeapon($ev->getPlayer()) == "활") {
                $this->plugin->Bow($ev->getPlayer());
            }
            if ($this->plugin->getWeapon($ev->getPlayer()) == "스태프") {
                $this->plugin->Staff($ev->getPlayer());
            }
        } elseif ($pk instanceof LevelSoundEventPacket && ($pk->sound == LevelSoundEventPacket::SOUND_ATTACK_NODAMAGE || $pk->sound == LevelSoundEventPacket::SOUND_ATTACK_STRONG || $pk->sound == LevelSoundEventPacket::SOUND_ATTACK)) {
            $this->plugin->Swing($ev->getPlayer());
            if (stripos($ev->getPlayer()->getInventory()->getIteminHand()->getCustomName(), "몽둥이") !== false) {
                return;
            }
            if ($this->plugin->getWeapon($ev->getPlayer()) == "검") {
                $ev->getPlayer()->getLevel()->broadcastLevelSoundEvent($ev->getPlayer()->add(0, 0.62, 0), 183);
            }
        }
    }

    public function onQuit(PlayerQuitEvent $ev) {
        if (isset($this->plugin->eqinv[$ev->getPlayer()->getName()]))
            unset($this->plugin->eqinv[$ev->getPlayer()->getName()]);
    }

    public function onClose(InventoryCloseEvent $ev) {
        if ($ev->getPlayer() instanceof Player) {
            if (isset($this->plugin->eqinv[$ev->getPlayer()->getName()]))
                unset($this->plugin->eqinv[$ev->getPlayer()->getName()]);
        }
    }
}
