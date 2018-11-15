<?php
/**
 *  _____    ____    ____   __  __  __  ______
 * |  __ \  / __ \  / __ \ |  \/  |/_ ||____  |
 * | |__) || |  | || |  | || \  / | | |    / /
 * |  _  / | |  | || |  | || |\/| | | |   / /
 * | | \ \ | |__| || |__| || |  | | | |  / /
 * |_|  \_\ \____/  \____/ |_|  |_| |_| /_/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

declare(strict_types=1);

namespace room17\Duels\match;


use room17\Duels\arena\Arena;
use room17\Duels\Duels;
use room17\Duels\event\match\MatchStartEvent;
use room17\Duels\event\match\MatchStopEvent;
use room17\Duels\session\Session;

class MatchManager {
    
    /** @var Duels */
    private $loader;
    
    /** @var Match[] */
    private $matches = [];
    
    /**
     * MatchManager constructor.
     * @param Duels $loader
     */
    public function __construct(Duels $loader) {
        $this->loader = $loader;
        $loader->getScheduler()->scheduleRepeatingTask(new MatchHeartbeat($this), 20);
        $loader->getServer()->getPluginManager()->registerEvents(new MatchListener($this), $loader);
    }
    
    /**
     * @return Duels
     */
    public function getLoader(): Duels {
        return $this->loader;
    }
    
    /**
     * @return Match[]
     */
    public function getMatches(): array {
        return $this->matches;
    }
    
    /**
     * @param int $identifier
     * @return null|Match
     */
    public function getMatch(int $identifier): ?Match {
        return $this->matches[$identifier] ?? null;
    }
    
    /**
     * @param Session $firstSession
     * @param Session $secondSession
     * @param null|Arena $arena
     * @return bool
     */
    public function startMatch(Session $firstSession, Session $secondSession, ?Arena $arena = null): bool {
        $identifier = count($this->matches) + 1;
        
        $event = new MatchStartEvent($match = new Match($this, $identifier, ($arena ?? $this->loader->getArenaManager()->getRandomArena()),
            $firstSession, $secondSession));
        $this->loader->getServer()->getPluginManager()->callEvent($event);
        
        if($event->isCancelled()) {
            $this->loader->getLogger()->debug("Couldn't create the match $identifier ($firstSession vs $secondSession) because the event was cancelled");
            return false;
        }
        
        $arena = $match->getArena();
        
        $firstSession->setMatch($match);
        $firstSession->sendLocalizedMessage("JOINED_MATCH", [
            "enemy" => $secondSession
        ]);
        $firstSession->getOwner()->teleport($arena->getFirstSpawn());
        
        $secondSession->setMatch($match);
        $secondSession->sendLocalizedMessage("JOINED_MATCH", [
            "enemy" => $firstSession
        ]);
        $secondSession->getOwner()->teleport($arena->getSecondSpawn());
        
        $this->matches[$identifier] = $match;
        return true;
    }
    
    /**
     * @param int $identifier
     */
    public function stopMatch(int $identifier): void {
        if(isset($this->matches[$identifier])) {
            $this->loader->getServer()->getPluginManager()->callEvent(new MatchStopEvent($this->matches[$identifier]));
            unset($this->matches[$identifier]);
        } else {
            $this->loader->getLogger()->warning("Trying to stop a match (id: $identifier) that does not exist");
        }
    }
    
}