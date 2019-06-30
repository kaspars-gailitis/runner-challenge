<?php

namespace App\Services;

use App\Models\ChallengeModel;
use App\Models\TeamModel;
use App\Models\TotalsModel;
use App\Models\UserModel;
use RedBeanPHP\R;

class TeamService
{
    /**
     * @param ChallengeModel $challenge
     * @return TeamModel
     */
    public function addTeam(ChallengeModel $challenge): TeamModel
    {
        $team = new TeamModel;
        $team->challengeId = $challenge->id;
        $team->name = "";
        $team->captainId = 0;
        $team->imageId = 0;
        $team->createdAt = time();
        $team->save();

        $team->name = 'Team #' . $team->id;
        $team->save();

        return $team;
    }

    /**
     * @param ChallengeModel $challenge
     * @return TeamModel[]
     */
    public function getAll(ChallengeModel $challenge): array
    {
        $beans = R::findAll('teams', 'challenge_id = ?', [$challenge->id]);

        return array_map(function ($bean) {
            return TeamModel::fromBean($bean);
        }, $beans);
    }

    /**
     * @param int $teamId
     * @param ChallengeModel $challenge
     * @return TeamModel|null
     */
    public function getById(?int $teamId, ?ChallengeModel $challenge = null): ?TeamModel
    {
        if (!$teamId) {
            return null;
        }

        if ($challenge) {
            $bean = R::findOne('teams', 'id = ? AND challenge_id = ?', [$teamId, $challenge->id]);
        } else {
            $bean = R::findOne('teams', 'id = ?', [$teamId]);
        }

        if ($bean) {
            return TeamModel::fromBean($bean);
        }

        return null;
    }

    /**
     * @param TeamModel $team
     * @param UserModel[] $users
     */
    public function assignUsers(TeamModel $team, array $users)
    {
        foreach ($users as $user) {
            if ($user->teamId) {
                continue;
            }
            $user->teamId = $team->id;
            $user->save();
        }

        $this->recalculateTeamScore($team);
    }

    public function unassignUser(?UserModel $user)
    {
        $team = $this->getById($user->teamId);
        $user->teamId = null;
        $user->save();

        if ($team) {
            $this->recalculateTeamScore($team);
        }
    }

    public function deleteTeam(TeamModel $team)
    {
        $users = UserModel::findByTeam($team->id);
        foreach ($users as $v) {
            $v->teamId = null;
            $v->save();
        }

        $team->delete();
    }

    public function recalculateTeamScore(TeamModel $team)
    {
        $scores = R::getRow('
            SELECT SUM(distance) AS total_distance, SUM(duration) AS total_duration FROM activities a
            JOIN users u ON u.id = a.user_id
            WHERE u.team_id = ?
            GROUP BY u.team_id
        ', [$team->id]);

        $team->totalDistance = (float)$scores['total_distance'] ?? 0;
        $team->totalDuration = (float)$scores['total_duration'] ?? 0;
        $team->save();
    }

    /**
     * @param TeamModel|null $team
     * @return TotalsModel[]
     */
    public function getUserTotals(?TeamModel $team): array
    {
        if (!$team) {
            return [];
        }

        $totalsRaw = R::getAll('
            SELECT 
                   u.id, 
                   u.name, 
                   SUM(distance) AS total_distance, 
                   SUM(duration) AS total_duration, 
                   MAX(a.activity_at) AS last_activity_at,
                   COUNT(a.id) AS activity_count
            FROM users u
            LEFT OUTER JOIN activities a ON u.id = a.user_id
            WHERE u.team_id = ?
            GROUP BY u.id
            ORDER BY total_distance DESC
        ', [$team->id]);

        return array_map(function ($r) {
            $t = new TotalsModel;
            $t->id = (int)$r['id'];
            $t->name = $r['name'];
            $t->distance = (float)$r['total_distance'];
            $t->duration = (int)$r['total_duration'];
            $t->lastActivityAt = (int)$r['last_activity_at'];
            $t->activityCount = (int)$r['activity_count'];
            return $t;
        }, $totalsRaw);
    }
}