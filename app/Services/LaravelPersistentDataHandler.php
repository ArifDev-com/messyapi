<?php
namespace App\Services;

use Facebook\PersistentData\PersistentDataInterface;
use Illuminate\Support\Facades\Session;

class LaravelPersistentDataHandler implements PersistentDataInterface
{
    /**
     * Session key prefix for Facebook SDK data
     */
    protected string $sessionPrefix = 'fb_';

    /**
     * Constructor
     */
    public function __construct(?string $sessionPrefix = null)
    {
        if ($sessionPrefix) {
            $this->sessionPrefix = $sessionPrefix;
        }
    }

    /**
     * Get a value from persistent storage
     *
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        $sessionKey = $this->sessionPrefix . $key;
        $value = Session::get($sessionKey);

        // Log for debugging (remove in production)
        \Log::debug('Facebook SDK GET', [
            'key' => $sessionKey,
            'value' => $value,
            'session_id' => Session::getId()
        ]);

        return $value;
    }

    /**
     * Set a value in persistent storage
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set($key, $value)
    {
        $sessionKey = $this->sessionPrefix . $key;

        // Log for debugging (remove in production)
        \Log::debug('Facebook SDK SET', [
            'key' => $sessionKey,
            'value' => $value,
            'session_id' => Session::getId()
        ]);

        Session::put($sessionKey, $value);
    }

    /**
     * Delete a value from persistent storage
     *
     * @param string $key
     * @return void
     */
    public function delete($key)
    {
        $sessionKey = $this->sessionPrefix . $key;

        // Log for debugging (remove in production)
        \Log::debug('Facebook SDK DELETE', [
            'key' => $sessionKey,
            'session_id' => Session::getId()
        ]);

        Session::forget($sessionKey);
    }
}
