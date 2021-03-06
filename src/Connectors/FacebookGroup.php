<?php

namespace Borfast\Socializr\Connectors;

use Borfast\Socializr\Exceptions\GenericPostingException;
use Borfast\Socializr\Post;
use Borfast\Socializr\Group;
use Borfast\Socializr\Response;

// use \Requests;

class FacebookGroup extends Facebook
{
    /** @var Group */
    protected $group = null;

    public function post(Post $post)
    {
        $group = $this->getGroup();

        if (empty($post->media)) {
            $path = '/'.$group->id.'/feed';

            $msg  = $post->title;
            $msg .= "\n\n";
            $msg .= $post->body;
            $msg = trim($msg);

            $params = [
                // 'caption' => $post->title,
                'description' => '',
                'link' => $post->url,
                'message' => $msg
            ];
        } else {
            $path = '/'.$group->id.'/photos';

            $msg  = $post->title;
            $msg .= "\n\n";
            $msg .= $post->body;
            $msg .= "\n";
            $msg .= $post->url;

            $params = [
                'url' => $post->media[0],
                'caption' => $msg
            ];
        }

        $method = 'POST';

        $result = $this->request($path, $method, $params);
        $json_result = json_decode($result, true);

        // If there's no ID, the post didn't go through
        if (!isset($json_result['id'])) {
            $msg = "Unknown error posting to Facebook group.";
            throw new GenericPostingException($msg, 1);
        }

        $response = new Response;
        $response->setRawResponse($result); // This is already JSON.
        $response->setProvider('Facebook');
        $response->setPostId($json_result['id']);

        return $response;
    }

    public function getGroup()
    {
        if (is_null($this->group)) {
            $path = '/' . $this->id . '?fields=id,name,icon';
            $result = $this->request($path);
            $json_result = json_decode($result, true);

            $mapping = [
                'id' => 'id',
                'name' => 'name',
                'picture' => 'icon'
            ];

            $this->group = Group::create($mapping, $json_result);
            $this->group->picture = $json_result['icon'];
            $this->group->link = 'https://www.facebook.com/groups/' . $json_result['id'];
            $this->group->can_post = true;
            $this->group->provider = static::$provider;
            $this->group->raw_response = $result;
        }

        return $this->group;
    }

    /**
     * Get the number of memebers this group has.
     */
    public function getStats()
    {
        return $this->getMembersCount();
    }


    /***************************************************************************
     *
     * From here on these are FacebookGroup-specific methods that should not be
     * accessed from other classes.
     *
     **************************************************************************/

    protected function getMembersCount()
    {
        $group = $this->getGroup();

        $path = '/'.$group->id.'/members';
        $result = $this->request($path);

        $response = json_decode($result);
        $response = count($response->data);

        return $response;
    }

}
