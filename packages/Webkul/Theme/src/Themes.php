<?php

namespace Webkul\Theme;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Webkul\Theme\Exceptions\ViterNotFound;

class Themes
{
    /**
     * Contains current activated theme code.
     *
     * @var string
     */
    protected $activeTheme = null;

    /**
     * Contains all themes.
     *
     * @var array
     */
    protected $themes = [];

    /**
     * Contains laravel default view paths.
     *
     * @var array
     */
    protected $laravelViewsPath;

    /**
     * Create a new themes instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->laravelViewsPath = Config::get('view.paths');

        $this->loadThemes();
    }

    /**
     * Return list of all registered themes.
     *
     * @return array
     */
    public function all()
    {
        return $this->themes;
    }

    /**
     * Return list of registered themes.
     *
     * @return array
     */
    public function getChannelThemes()
    {
        $themes = config('themes.shop', []);

        $channelThemes = [];

        foreach ($themes as $code => $data) {
            $channelThemes[] = new Theme(
                $code,
                $data['name'] ?? '',
                $data['assets_path'] ?? '',
                $data['views_path'] ?? '',
                isset($data['vite']) ? $data['vite'] : [],
            );

            if (! empty($data['parent'])) {
                $parentThemes[$code] = $data['parent'];
            }
        }

        return $channelThemes;
    }

    /**
     * Check if specified exists.
     *
     * @return bool
     */
    public function exists(string $themeName)
    {
        foreach ($this->themes as $theme) {
            if ($theme->code == $themeName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prepare all themes.
     *
     * @return void
     */
    public function loadThemes()
    {
        $parentThemes = [];

        if (Str::contains(request()->url(), config('app.admin_url').'/')) {
            $themes = config('themes.admin', []);
        } else {
            $themes = config('themes.shop', []);
        }

        foreach ($themes as $code => $data) {
            $this->themes[] = new Theme(
                $code,
                $data['name'] ?? '',
                $data['assets_path'] ?? '',
                $data['views_path'] ?? '',
                $data['views_namespace'] ?? null,
                $data['vite'] ?? [],
            );

            if (! empty($data['parent'])) {
                $parentThemes[$code] = $data['parent'];
            }
        }

        foreach ($parentThemes as $childCode => $parentCode) {
            $child = $this->find($childCode);

            if ($this->exists($parentCode)) {
                $parent = $this->find($parentCode);
            } else {
                $parent = new Theme($parentCode);
            }

            $child->setParent($parent);
        }
    }

    /**
     * Enable theme.
     *
     * @return \Webkul\Theme\Theme
     */
    public function set(string $themeName)
    {
        if ($this->exists($themeName)) {
            $theme = $this->find($themeName);
        } else {
            $theme = new Theme($themeName);
        }

        $this->activeTheme = $theme;

        $paths = $theme->getViewPaths();

        foreach ($this->laravelViewsPath as $path) {
            if (! in_array($path, $paths)) {
                $paths[] = $path;
            }
        }

        Config::set('view.paths', $paths);

        $themeViewFinder = app('view.finder');

        $themeViewFinder->setPaths($paths);

        return $theme;
    }

    /**
     * Get current theme.
     *
     * @return \Webkul\Theme\Theme
     */
    public function current()
    {
        return $this->activeTheme ?? null;
    }

    /**
     * Get current theme's name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->current()?->name ?? '';
    }

    /**
     * Find a theme by it's name.
     *
     * @return \Webkul\Theme\Theme
     */
    public function find(string $themeName)
    {
        foreach ($this->themes as $theme) {
            if ($theme->code == $themeName) {
                return $theme;
            }
        }

        throw new Exceptions\ThemeNotFound($themeName);
    }

    /**
     * Original view paths defined in `config.view.php`.
     *
     * @return array
     */
    public function getLaravelViewPaths()
    {
        return $this->laravelViewsPath;
    }

    /**
     * Return the asset URL of the current theme if a theme is found; otherwise, check from the namespace.
     *
     * @return string
     */
    public function url(string $filename, ?string $namespace = null)
    {
        $url = trim($filename, '/');

        /**
         * If the namespace is null, it means the theming system is activated. We use the request URI to
         * detect the theme and provide Vite assets based on the current theme.
         */
        if (empty($namespace)) {
            // If no theme is currently set, set a default theme based on the context
            if ($this->current() === null) {
                $this->setDefaultTheme();
            }
            
            return $this->current()->url($url);
        }

        /**
         * If a namespace is provided, it means the developer knows what they are doing and must create the
         * registry in the provided configuration. We will analyze based on that.
         */
        $viters = config('bagisto-vite.viters');

        if (empty($viters[$namespace])) {
            throw new ViterNotFound($namespace);
        }

        $viteUrl = trim($viters[$namespace]['package_assets_directory'], '/').'/'.$url;

        return Vite::useHotFile($viters[$namespace]['hot_file'])
            ->useBuildDirectory($viters[$namespace]['build_directory'])
            ->asset($viteUrl);
    }

    /**
     * Set bagisto vite in current theme.
     *
     * @param  mixed  $entryPoints
     * @return mixed
     */
    public function setBagistoVite($entryPoints, ?string $namespace = null)
    {
        /**
         * If the namespace is null, it means the theming system is activated. We use the request URI to
         * detect the theme and provide Vite assets based on the current theme.
         */
        if (empty($namespace)) {
            // If no theme is currently set, set a default theme based on the context
            if ($this->current() === null) {
                $this->setDefaultTheme();
            }
            
            return $this->current()->setBagistoVite($entryPoints);
        }

        /**
         * If a namespace is provided, it means the developer knows what they are doing and must create the
         * registry in the provided configuration. We will analyze based on that.
         */
        $viters = config('bagisto-vite.viters');

        if (empty($viters[$namespace])) {
            throw new ViterNotFound($namespace);
        }

        return Vite::useHotFile($viters[$namespace]['hot_file'])
            ->useBuildDirectory($viters[$namespace]['build_directory'])
            ->withEntryPoints($entryPoints);
    }

    /**
     * Set a default theme when none is currently active.
     * This prevents errors when bagisto_asset() is called outside of web requests.
     *
     * @return void
     */
    protected function setDefaultTheme()
    {
        // Determine if we're in admin context or shop context
        $isAdminContext = false;
        
        // Check if we're in a console command or if the request URL contains admin path
        if (app()->runningInConsole()) {
            // For console commands, default to shop theme unless explicitly admin
            $isAdminContext = false;
        } elseif (request() && request()->url()) {
            $isAdminContext = Str::contains(request()->url(), config('app.admin_url').'/');
        }

        // Set the appropriate default theme
        if ($isAdminContext) {
            $defaultTheme = config('themes.admin-default', 'default');
        } else {
            $defaultTheme = config('themes.shop-default', 'default');
        }

        $this->set($defaultTheme);
    }
}
