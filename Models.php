<?php declare(strict_types=1);
/**
 * Sonic
 * @copyright 2020 Haunted Bees Productions
 * @author Sean Finch <fench@hauntedbees.com>
 * @license https://www.gnu.org/licenses/agpl-3.0.en.html GNU Affero General Public License
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * @see https://github.com/HauntedBees/BeeAPI
 */

/* #region Feedback */
class SonicFeedback { 
    public string $path;
    public string $text; 
    public string $name;
    public string $contact;
    public ?int $issue;
}
class SonicPostFeedback extends SonicFeedback {
    public string $captcha;
    public string $token;
}
class SonicSecureFeedback extends SonicFeedback {
    public int $id;
    public string $date;
    public ?string $issueParent;
}
/* #endregion */
/* #region Issues */
class SonicSecureIssue {
    public int $id;
    public int $companyid;
    public string $issue;
    public int $type;
    public string $sourceurl;
    public ?string $contentwarning;
    public string $startdate;
    public ?string $enddate;
    public bool $ongoing;
}
class SonicIssue {
    public int $entityId;
    public string $entityName;
    public int $issueTypeId;
    public ?string $issueType;
    public ?string $issueIcon;
    public ?string $issueColor;
    public ?int $id;
    public ?string $issue;
    public ?string $sourceurl;
    public ?string $startdate;
    public ?string $enddate;
    public ?bool $ongoing;
    public ?string $contentwarning;
    public ?bool $showOnTop;
    public string $namepath;
}
/* #endregion */
/* #region Issue Types */
class SonicIssueType {
    public int $id;
    public string $name;
    public string $icon;
    public string $color;
    public bool $showOnTop;
}
/* #endregion */
/* #region Categories */
class SonicCategory {
    public int $id;
    public string $name;
    public string $icon;
}
class SonicSecureCategory extends SonicCategory {
    public int $count;
}
class SonicCategoryWithParents extends SonicCategory {
    public array $parents;
}
/* #endregion */
/* #region Graphs */
class SonicGraphInfo {
    public int $parentId;
    public string $parentName;
    public int $childId;
    public string $childName;
    public string $asOfDate;
    public int $parentx;
    public int $parenty;
    public string $parentImg;
    public ?int $childx;
    public ?int $childy;
    public string $childImg;
    public string $relationtype;
    public bool $me;
}
class SonicNode {
    public string $id;
    public string $name;
    public string $img;
    public ?int $iconx;
    public ?int $icony;
}
class SonicCategoryNode {
    public int $id;
    public string $icon;
    public string $name;
    public int $count;
}
class SonicLink {
    public int $source;
    public int $target;
}
class SonicCompanyLink extends SonicLink {
    public int $relationtype;
    public string $asOfDate;
}
/* #endregion */
/* #region Companies */
class SonicCompanyList {
    public int $id;
    public string $name;
    public string $categoryname;
    public ?string $categoryicon;
    public int $issues;
    public int $children;
}
class SonicCompanySearchResult {
    public string $name;
    public int $id;
    public ?string $parent;
}
class SonicCompany {
    public int $id;
    public string $name;
    public int $type;
    public string $description;
    public ?string $typename;
    public string $img;
    public string $iconx;
    public string $icony;
    // below aren't populated by SQL query
    public array $synonyms;
    public ?array $children = [];
    public ?bool $hasAddtlRelationships;
    public array $parents = [];
}
class SonicSecureCompany extends SonicCompany {
    public string $newtype;
    public array $investors = [];
    public array $miscrelationships = [];
}
class SonicParentCompany {
    public int $id;
    public string $name;
    public int $depth;
    public int $chain;
}
class SonicChildCompany {
    public int $child;
    public string $text;
}
class SonicCompanyChain {
    public string $text;
    public int $rootid;
    public string $rootname;
    public function __construct(int $id, string $name) {
        $this->text = $name;
        $this->rootid = $id;
        $this->rootname = $name;
    }
}
/* #endregion */
?>