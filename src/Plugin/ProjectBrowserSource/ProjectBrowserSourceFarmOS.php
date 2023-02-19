<?php

namespace Drupal\farm_project_browser\Plugin\ProjectBrowserSource;

use Drupal\project_browser\Plugin\ProjectBrowserSourceBase;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;

/**
 * Project Browser Source Plugin for farmOS addons.
 *
 * @ProjectBrowserSource(
 *   id = "project_browser_source_farmos",
 *   label = @Translation("farmOS modules"),
 *   description = @Translation("Collection of farmOS modules from different sources."),
 * )
 */
class ProjectBrowserSourceFarmOS extends ProjectBrowserSourceBase {

  /**
   * {@inheritdoc}
   */
  public function getProjects(array $query = []): ProjectsResultsPage {

    $contribModules = $this->getContribModules();

    // Filter by module id.
    $machineName = $query['machine_name'] ?? '';
    if (!empty($machineName)) {
      $contribModules = array_filter($contribModules, fn($module) => $module['id'] === $machineName);
    }

    // Filter by search query.
    $search = $query['search'] ?? '';
    if (!empty($search)) {
      $search = strtolower($search);
      $contribModules = array_filter(
        $contribModules,
        fn($module) =>
          str_contains(strtolower($module['name']), $search)
          || str_contains(strtolower($module['desc']), $search)
          || str_contains(strtolower($module['src']), $search)
      );
    }

    // Filter by categories.
    if (isset($query['categories']) && !empty($query['categories'])) {
      $selectedCategories = explode(',', $query['categories']);
      $contribModules = array_filter(
        $contribModules,
        fn($module) =>
          !empty($module['categories'])
          && is_array($module['categories'])
          && array_intersect($module['categories'], $selectedCategories)
      );
    }

    // Handle pagination.
    $total = count($contribModules);
    $page = intval($query['page']);
    $limit = intval($query['limit']);
    if ($limit < 1) {
      $limit = 12;
    }
    $contribModules = array_splice($contribModules, $page * $limit, $limit);

    // Prepare projects list.
    $projects = [];
    foreach ($contribModules as $module) {

      $categories = [];
      foreach ($module['categories'] as $category) {
        $categories[] = [
          'id' => $category,
          'name' => $category,
        ];
      }

      $url = $module['drupal.org'] ?? $module['src'];
      $projects[] = (new Project())
        ->setAuthor([])
        ->setProjectUrl($url)
        ->setCreatedTimestamp(time())
        ->setChangedTimestamp(time())
        ->setProjectTitle($module['name'])
        ->setId($module['id'])
        ->setSummary([
          'summary' => $module['desc'],
          'value' => $module['desc'] . '<br><a href="' . $url . '">' . $url . '</a>',
        ])
        ->setLogo([])
        ->setModuleCategories($categories)
        ->setMachineName($module['id'])
        ->setComposerNamespace($module['composer'] ?? '')
        ->setIsCompatible(TRUE)
        ->setProjectUsageTotal(0)
        ->setProjectStarUserCount(0)
        ->setImages([])
        ->setProjectStatus(1)
        ->setIsCovered(FALSE)
        ->setIsActive(TRUE)
        ->setIsMaintained(TRUE);
    }

    // Return projects result page.
    return new ProjectsResultsPage($total, $projects, (string) $this->getPluginDefinition()['label'], $this->getPluginId());
  }

  /**
   * Get contrib modules from farmos-community-projects list repo.
   */
  protected function getContribModules(): array {
    $data = file_get_contents("https://raw.githubusercontent.com/wotnak/farmos-community-projects/main/projects.json");
    if (!is_string($data)) {
      return [];
    }
    $projects = json_decode($data, associative: TRUE);
    if (!is_array($projects) || empty($projects)) {
      return [];
    }
    return array_filter($projects, fn($project) => $project['type'] === 'contrib-module');
  }

  /**
   * {@inheritdoc}
   */
  public function getCategories(): array {
    $contribModules = $this->getContribModules();
    $categories = [];
    foreach ($contribModules as $module) {
      if (!is_array($module['categories']) || empty($module['categories'])) {
        continue;
      }
      foreach ($module['categories'] as $category) {
        $categories[] = $category;
      }
    }
    $categories = array_values(array_unique($categories));
    sort($categories);
    $categoryList = [];
    foreach ($categories as $category) {
      $categoryList[] = [
        'id' => $category,
        'name' => $category,
      ];
    }
    return $categoryList;
  }

}
