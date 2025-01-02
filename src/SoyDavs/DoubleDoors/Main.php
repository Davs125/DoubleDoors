<?php
namespace SoyDavs\DoubleDoors;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\block\Door;
use pocketmine\block\FenceGate;
use pocketmine\block\TrapDoor;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class Main extends PluginBase implements Listener {
    private array $config = [];
    
    protected function onEnable(): void {
        $this->saveDefaultConfig();
        $this->config = [
            "enableRecursiveOpening" => $this->getConfig()->get("enableRecursiveOpening", true),
            "recursiveOpeningMaxBlocksDistance" => $this->getConfig()->get("recursiveOpeningMaxBlocksDistance", 10),
            "enableDoors" => $this->getConfig()->get("enableDoors", true),
            "enableFenceGates" => $this->getConfig()->get("enableFenceGates", true),
            "enableTrapdoors" => $this->getConfig()->get("enableTrapdoors", true)
        ];
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        
        if ($player->isSneaking()) {
            return;
        }

        if (($block instanceof Door && !$this->config["enableDoors"]) ||
            ($block instanceof FenceGate && !$this->config["enableFenceGates"]) ||
            ($block instanceof TrapDoor && !$this->config["enableTrapdoors"])) {
            return;
        }

        $this->handleDoubleBlock($block, $event);
    }

    private function handleDoubleBlock($block, PlayerInteractEvent $event): void {
        $processed = [];
        $newState = $this->getNewState($block);
        if ($newState === null) {
            return; // Si el bloque no tiene estado de apertura, salimos
        }

        $event->cancel();
        $this->processConnectedBlocks($block, $processed, $event->getPlayer(), $block->getPosition(), 0, $newState);
    }

    private function getNewState($block): ?bool {
        if ($block instanceof Door || $block instanceof FenceGate || $block instanceof TrapDoor) {
            return !$block->isOpen();
        }
        return null;
    }

    private function processConnectedBlocks($originalBlock, array &$processed, Player $player, Vector3 $pos, int $distance, bool $newState): void {
        if ($distance > $this->config["recursiveOpeningMaxBlocksDistance"] || !$this->config["enableRecursiveOpening"]) {
            return;
        }

        $world = $player->getWorld();
        
        for ($x = -1; $x <= 1; $x++) {
            for ($y = -1; $y <= 1; $y++) {
                for ($z = -1; $z <= 1; $z++) {
                    $checkPos = $pos->add($x, $y, $z);
                    $blockKey = "{$checkPos->x},{$checkPos->y},{$checkPos->z}";
                    $checkBlock = $world->getBlock($checkPos);
                    
                    if ($checkBlock::class === $originalBlock::class && 
                        !isset($processed[$blockKey]) &&
                        ($checkPos->x !== $pos->x || $checkPos->y !== $pos->y || $checkPos->z !== $pos->z)) {
                        
                        $processed[$blockKey] = true;
                        
                        if ($checkBlock instanceof Door || $checkBlock instanceof FenceGate || $checkBlock instanceof TrapDoor) {
                            $checkBlock->setOpen($newState);
                        }
                        
                        $world->setBlock($checkPos, $checkBlock);
                        
                        if ($this->config["enableRecursiveOpening"]) {
                            $this->processConnectedBlocks($originalBlock, $processed, $player, $checkPos, $distance + 1, $newState);
                        }
                    }
                }
            }
        }
    }
}
