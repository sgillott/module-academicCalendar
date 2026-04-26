/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

(function (window, document) {
    'use strict';

    window.AcademicCalendarModule = window.AcademicCalendarModule || {};

    window.AcademicCalendarModule.initSettingsManage = function (options) {
        options = options || {};

        var table = document.getElementById('acEventTypesTable');
        var checkAll = table ? table.querySelector('input.acEventTypesCheckAll[type="checkbox"]') : null;
        var classificationStyles = options.classificationStyles || {};
        var previewTargets = options.previewTargets || [];
        var settingsForm = document.getElementById('settings_manage');
        var deleteConfirm = options.deleteConfirm || 'Are you sure?';

        var boxes = function () {
            if (!table) {
                return [];
            }

            return Array.prototype.slice.call(
                table.querySelectorAll('input.acEventTypeVisible[type="checkbox"], .acEventTypeVisible input[type="checkbox"]')
            );
        };

        var classificationSelects = table
            ? Array.prototype.slice.call(table.querySelectorAll('select[name^="typeClassification["]'))
            : [];

        var submitSettingsForm = function () {
            if (!settingsForm) {
                return;
            }

            if (typeof settingsForm.requestSubmit === 'function') {
                settingsForm.requestSubmit();
            } else {
                settingsForm.submit();
            }
        };

        var updatePreview = function (selectName, previewId) {
            var selectEl = document.querySelector('select[name="' + selectName + '"]');
            var previewEl = document.getElementById(previewId);
            if (!selectEl || !previewEl) {
                return;
            }

            var previewMap = {};
            try {
                previewMap = JSON.parse(previewEl.getAttribute('data-preview-map') || '{}');
            } catch (error) {
                previewMap = {};
            }

            var render = function () {
                var value = selectEl.value;
                previewEl.textContent = previewMap[value] || '';
            };

            selectEl.addEventListener('change', render);
            render();
        };

        var updateCheckAllState = function () {
            if (!checkAll) {
                return;
            }

            var visibleBoxes = boxes();
            var checkedCount = visibleBoxes.filter(function (box) {
                return box.checked;
            }).length;

            checkAll.checked = visibleBoxes.length > 0 && checkedCount === visibleBoxes.length;
            checkAll.indeterminate = checkedCount > 0 && checkedCount < visibleBoxes.length;
        };

        if (checkAll) {
            checkAll.addEventListener('change', function () {
                boxes().forEach(function (box) {
                    box.checked = !!checkAll.checked;
                });
                updateCheckAllState();
            });
        }

        boxes().forEach(function (box) {
            box.addEventListener('change', updateCheckAllState);
        });
        updateCheckAllState();

        classificationSelects.forEach(function (selectEl) {
            var renderClassificationStyle = function () {
                var row = selectEl.closest('tr');
                if (!row) {
                    return;
                }

                var key = selectEl.value || 'none';
                var style = classificationStyles[key] || classificationStyles.none || {};
                row.style.setProperty('--acClassificationHighlight', style.highlight || '');
                row.style.setProperty('--acClassificationBorder', style.border || '');
                row.style.setProperty('--acClassificationBorderWidth', style.border ? '2px' : '1px');
            };

            renderClassificationStyle();
            selectEl.addEventListener('change', renderClassificationStyle);
        });

        var addClassificationButton = document.getElementById('acAddClassificationButton');
        if (addClassificationButton && settingsForm && settingsForm.addAssessmentClassification) {
            addClassificationButton.addEventListener('click', function () {
                settingsForm.addAssessmentClassification.value = 'Y';
                submitSettingsForm();
            });
        }

        document.querySelectorAll('.acDeleteClassificationButton').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!settingsForm || !settingsForm.deleteAssessmentClassification) {
                    return;
                }

                var confirmed = window.confirm(deleteConfirm);
                if (!confirmed) {
                    return;
                }

                settingsForm.deleteAssessmentClassification.value = button.getAttribute('data-classification-key') || '';
                submitSettingsForm();
            });
        });

        previewTargets.forEach(function (target) {
            if (!target || !target.selectName || !target.previewId) {
                return;
            }

            updatePreview(target.selectName, target.previewId);
        });
    };

    window.AcademicCalendarModule.initOverviewTooltips = function () {
        var tooltip = document.createElement('div');
        tooltip.className = 'acOverviewTooltip';
        tooltip.hidden = true;
        document.body.appendChild(tooltip);

        var activeNode = null;

        function hideTooltip() {
            tooltip.hidden = true;
            tooltip.textContent = '';
            activeNode = null;
        }

        function positionTooltip(node) {
            if (!node || tooltip.hidden) {
                return;
            }

            var rect = node.getBoundingClientRect();
            var margin = 10;
            var spacing = 8;
            var viewportWidth = window.innerWidth;
            var viewportHeight = window.innerHeight;
            var tooltipRect = tooltip.getBoundingClientRect();

            var top = rect.bottom + spacing;
            if (top + tooltipRect.height > viewportHeight - margin) {
                top = rect.top - tooltipRect.height - spacing;
            }
            if (top < margin) {
                top = margin;
            }

            var left = rect.left;
            if (left + tooltipRect.width > viewportWidth - margin) {
                left = viewportWidth - tooltipRect.width - margin;
            }
            if (left < margin) {
                left = margin;
            }

            tooltip.style.top = top + 'px';
            tooltip.style.left = left + 'px';
        }

        function showTooltip(node) {
            var text = node.getAttribute('data-tooltip');
            if (!text) {
                hideTooltip();
                return;
            }

            activeNode = node;
            tooltip.textContent = text;
            tooltip.hidden = false;
            positionTooltip(node);
        }

        document.querySelectorAll('.acOverviewSubjectLine[data-tooltip]').forEach(function (node) {
            node.addEventListener('mouseenter', function () {
                showTooltip(node);
            });
            node.addEventListener('mouseleave', hideTooltip);
            node.addEventListener('focus', function () {
                showTooltip(node);
            });
            node.addEventListener('blur', hideTooltip);
        });

        window.addEventListener('scroll', function () {
            if (activeNode) {
                positionTooltip(activeNode);
            }
        }, true);

        window.addEventListener('resize', function () {
            if (activeNode) {
                positionTooltip(activeNode);
            }
        });
    };
})(window, document);
