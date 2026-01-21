<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IInitialStateService;
use OCP\IL10N;

class PageController extends Controller {
    private IInitialStateService $initialStateService;
    private IL10N $l10n;

    public function __construct(
        string $appName,
        IRequest $request,
        IInitialStateService $initialStateService,
        IL10N $l10n
    ) {
        parent::__construct($appName, $request);
        $this->initialStateService = $initialStateService;
        $this->l10n = $l10n;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        // Load translations from JSON and provide via initial state
        $translations = $this->loadTranslations();
        $this->initialStateService->provideInitialState('timetracking', 'translations', $translations);
        
        return new TemplateResponse('timetracking', 'main');
    }

    /**
     * Load translations for the current user's language from JSON files
     */
    private function loadTranslations(): array {
        $lang = $this->l10n->getLanguageCode();
        $appPath = \OC_App::getAppPath('timetracking');
        
        // Try to load the user's language, fall back to English, then German
        $langFile = $appPath . '/l10n/' . $lang . '.json';
        if (!file_exists($langFile)) {
            // Try base language (e.g., 'de' from 'de_DE')
            $baseLang = explode('_', $lang)[0];
            $langFile = $appPath . '/l10n/' . $baseLang . '.json';
        }
        if (!file_exists($langFile)) {
            $langFile = $appPath . '/l10n/en.json';
        }
        if (!file_exists($langFile)) {
            $langFile = $appPath . '/l10n/de.json';
        }
        
        if (file_exists($langFile)) {
            $content = file_get_contents($langFile);
            $data = json_decode($content, true);
            return $data['translations'] ?? [];
        }
        
        return [];
    }
}

