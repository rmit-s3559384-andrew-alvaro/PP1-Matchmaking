<?php
/**
 * Created by PhpStorm.
 * User: campbellbrobbel
 * Date: 24/3/19
 * Time: 5:32 PM
 */

namespace App\MingleLibrary;
use App\MingleLibrary\Models\Blocked;
use App\MingleLibrary\Models\Ignored;
use App\MingleLibrary\Models\Like;
use App\MingleLibrary\Models\Match;
use App\MingleLibrary\Models\Postcode;
use App\MingleLibrary\Models\UserAttributes as UserAttributes;
use function GuzzleHttp\Psr7\str;

class MatchMaker
{
    /**
     * @param $attr
     * @param array $orderBy
     * @param int $limit
     * @param int $page
     * @return mixed
     *
     * Returns a list of UserAttribute objects who are likely good matches with a user.
     */
    function getPotentialMatches($attr, $orderBy=['score desc'], $limit=10, $page=1, $maxDistance=20000000, $age=10) {

        $minimumDateForAge = date('Y-m-d', strtotime("-$age year"));
        $postcode = $attr->postcodeObject;
        $openness = $attr['openness'];
        $conscientiousness = $attr['conscientiousness'];
        $extraversion = $attr['extraversion'];
        $agreeableness = $attr['agreeableness'];
        $neuroticism = $attr['neuroticism'];
        $rawWhereString = "round((abs(openness - $openness) + abs(conscientiousness - $conscientiousness) + abs(extraversion - $extraversion) + abs(agreeableness - $agreeableness) + abs(neuroticism - $neuroticism)) / 5,2) ";
        $latitude = $postcode['latitude'];
        $longitude = $postcode['longitude'];

        $orderByRaw = "";
        foreach ($orderBy as $key=>$item) {
            $orderByRaw = "$orderByRaw$item";
        }
        $ignored = Ignored::all()->where('user_id_1', $attr->user_id)->pluck(['user_id_2'])->toArray();
        //Blocked users
        $blocked = Blocked::all()->where('user_id', $attr->user_id)->pluck(['blocked_id'])->toArray();
        $matchedId1 = Match::all()->where('user_id_1', $attr->user_id)->pluck('user_id_2')->toArray();
        $matchedId2 = Match::all()->where('user_id_2', $attr->user_id)->pluck(['user_id_1'])->toArray();
        $liked = Like::all()->where('user_id_1', $attr->user_id)->pluck(['user_id_2'])->toArray();
        $ignoredUsers = array_merge($liked, $ignored, $matchedId1, $matchedId2, $blocked);
        $distanceString = "round(1.60934 * 2 * 3961 * asin(sqrt(pow(sin(radians(($latitude - latitude)/2)),2) + cos(radians($latitude)) * cos(radians(latitude)) * pow(sin(radians(($longitude-longitude)/2)),2))),2)";
        $attributes =  UserAttributes::join('postcodes', 'user_attributes.postcode', '=', 'postcodes.id')
            ->where('user_id','!=', $attr['user_id'])
            ->where('interested_in', $attr['gender'])
            ->where('gender', $attr['interested_in'])
            ->where('date_of_birth', '>', $minimumDateForAge)
            ->whereNotIn('user_attributes.user_id', $ignoredUsers)
            ->selectRaw("user_attributes.*, $rawWhereString as `score`, postcodes.latitude, postcodes.longitude, ".$distanceString." as distance")
            ->whereRaw($distanceString.'<'.strval($maxDistance))
            ->orderByRaw($orderByRaw)
            ->take($limit)
            ->skip(($page - 1) * $limit)
            ->get();

        return $attributes;
    }
}