var ProductLookupWizard = (function() {
    "use strict";

    return function(name, options) {

        var timer, widget, href, xhr;

        function clicked(event) {
            var parent = event.target.getParent('tr');
            if (parent.hasClass('row') && parent.hasClass('found')) {
                inject(parent);
            }
        }

        function mouseover(event) {
            var parent = event.target.getParent('tr');
            if (parent.hasClass('row') && parent.hasClass('found')) {
                widget.getElements('tr.found.row.selected').removeClass('selected');
                parent.addClass('selected');
            }
        }

        function inject(target) {
            target.removeClass('found')
                .addClass('selected')
                .inject(widget.getElement('tr.search'), 'before');
            target.getElement('td.col_1').innerHTML = '<input type="hidden" name=' + name + '[]" value="' + target.getProperty('data-id') + '" />' + target.getElement('td.col_1').innerHTML;
            target.getElements('td.operations img.delete-row').setStyle('display', '');
            window.fireEvent('productLookupWizard_postModified', {
                name: name,
                operation: 'selected',
                element: target
            });
        }

        function lookup() {
            widget.getElement('.search input.tl_text').setStyle('background-image', 'url(bundles/isotopepackagingslip/loading.gif)');
            var searchCtrl = widget.getElement('.search input.tl_text');
            var url = href + '&' + searchCtrl.name + '=' + searchCtrl.value;
            var ids = widget.getElements('td.col_1 input');
            ids.forEach(function(idElem, index) {
                url = url + '&' + idElem.name + '=' + idElem.value;
            });
            if (xhr) {
                xhr.abort();
            }
            xhr = new XMLHttpRequest();
            xhr.open('GET', url);
            xhr.addEventListener('load', function(event) {
                var rows;
                var text = xhr.responseText;
                try {
                    text = JSON.decode(text).content;
                } catch (error){}

                widget.getElements('.search input.tl_text').setStyle('background-image', 'none');
                widget.getElements('tr.found').destroy();

                rows = Elements.from(text, false);
                widget.getElement('tbody').adopt(rows);
                rows.forEach(function(row, index) {
                    row.addEvent('click', clicked);
                    row.addEvent('mouseover', mouseover);
                    if (row.hasClass('row_0')) {
                        row.addClass('selected');
                    }
                });

                window.fireEvent('productLookupWizard_postSearch', { name: name, result: text });
            });
            xhr.send();
        }

        widget = document.id('ctrl_' + name);
        href = window.location.href + '&tableLookupWizard=' + name;
        widget.getElement('.jserror').setStyle('display', 'none');
        widget.getElement('.search').setStyle('display', (((Browser.ie && Browser.version < 8) || (Browser.Engine && Browser.Engine.trident && Browser.Engine.version < 6)) ? 'block' : 'table-row'));

        widget.addEvent('keydown', function(event) {
            if (event.key == "enter") {
                var currentSelectedRow = widget.getElements('tr.found.selected')[0];
                if (currentSelectedRow) {
                    inject(currentSelectedRow);
                }
                event.preventDefault();
                event.stopPropagation();
            }
        }).addEvent('keyup', function(event) {
            var currentSelectedRow = widget.getElements('tr.found.row.selected')[0];
            var allRows = widget.getElements('tr.found.row');
            var firstRow = false;
            var lastRow = false;
            if (allRows.length > 0) {
                firstRow = allRows[0];
                lastRow = allRows[allRows.length - 1];
            }
            var isFirstRowSelected = false;
            var isLastRowSelected = false;
            if (firstRow && currentSelectedRow && firstRow.className == currentSelectedRow.className) {
                isFirstRowSelected = true;
            }
            if (lastRow && currentSelectedRow && lastRow.className == currentSelectedRow.className) {
                isLastRowSelected = true;
            }
            if (event.code == 40) {
                // Arrow down
                currentSelectedRow.removeClass('selected');
                var nextSelectedRow = currentSelectedRow.nextSibling;
                if (nextSelectedRow !== undefined && !isLastRowSelected) {
                    nextSelectedRow.addClass('selected');
                } else {
                    firstRow.addClass('selected');
                }
                event.preventDefault();
                event.stopPropagation();
            } else if (event.code == 38) {
                // Arrow up
                currentSelectedRow.removeClass('selected');
                var previousSelectedRow = currentSelectedRow.getPrevious();
                if (previousSelectedRow  !== undefined && !isFirstRowSelected) {
                    previousSelectedRow.addClass('selected');
                } else {
                    lastRow.addClass('selected');
                }
                event.preventDefault();
                event.stopPropagation();
            } else {
                clearTimeout(timer);
                timer = setTimeout(lookup, 300);
            }
        });
    };
})();
