(function($) {
    $(document).ready(function() {
        var titleInput = $('#title'); // Standard WordPress title input ID
        
        // If there's no standard title input, exit
        if (titleInput.length === 0) return;

        var feedbackElement = $('<p class="title-length-feedback"></p>');
        var previewElement = $('<div class="title-length-preview"><strong>SERP Preview:</strong> <span class="preview-text"></span></div>');

        titleInput.after(previewElement).after(feedbackElement);

        // --- Google SERP Title Emulation Settings ---
        var googleSerpStyles = {
            'font-family': 'arial, sans-serif',
            'font-size': '20px', // Google SERP title font size is generally 20px
            'font-weight': 'normal',
            'white-space': 'nowrap',
            'visibility': 'hidden',
            'position': 'absolute',
            'left': '-9999px'
        };
        var pixelLimitDesktop = 580; // Google's desktop pixel cut-off point
        var ellipsis = ' ...';       // Ellipsis string
        var ellipsisWidth = 0;       

        // Create a hidden span for pixel measurement
        var $measureSpan = $('<span>').css(googleSerpStyles).appendTo('body');

        // Function to measure text width in pixels
        function measureTextWidth(text) {
            $measureSpan.text(text);
            return $measureSpan.width();
        }

        // Calculate ellipsis width once
        ellipsisWidth = measureTextWidth(ellipsis);

        // Function to update the feedback
        function updateTitleFeedback() {
            var currentTitle = titleInput.val();
            var currentLength = currentTitle.length;
            var currentPixelWidth = measureTextWidth(currentTitle);

            var message = '';
            var className = '';
            var previewText = currentTitle;

            var charLimit = 60;
            var charWarningThreshold = 50;

            if (currentLength > charLimit) {
                message += 'Character count: ' + currentLength + ' (over ' + charLimit + ' recommended). ';
            } else if (currentLength >= charWarningThreshold) {
                message += 'Character count: ' + currentLength + ' (approaching ' + charLimit + ' recommended). ';
            } else {
                message += 'Character count: ' + currentLength + '. ';
            }

            message += 'Pixel width: ' + Math.round(currentPixelWidth) + 'px.';

            if (currentPixelWidth > pixelLimitDesktop) {
                className = 'error';
                message += ' This title is too long (' + Math.round(currentPixelWidth) + 'px > ' + pixelLimitDesktop + 'px) and will likely be truncated.';

                // Simulate truncation
                var truncatedTitle = '';
                var remainingPixelWidth = pixelLimitDesktop - ellipsisWidth;
                var tempTitle = '';

                var words = currentTitle.split(' ');
                for (var i = 0; i < words.length; i++) {
                    var word = words[i];
                    var testTitle = (tempTitle === '' ? word : tempTitle + ' ' + word);
                    if (measureTextWidth(testTitle) <= remainingPixelWidth) {
                        tempTitle = testTitle;
                    } else {
                        break;
                    }
                }
                truncatedTitle = tempTitle;

                if (truncatedTitle.length < currentTitle.length) {
                    previewText = truncatedTitle + ellipsis;
                } else {
                    previewText = currentTitle;
                }

            } else if (currentPixelWidth >= (pixelLimitDesktop * 0.85)) {
                className = 'warning';
                message += ' Approaching the ' + pixelLimitDesktop + 'px limit. Consider shortening for optimal display.';
            } else {
                className = 'success';
                message += ' Good length for optimal display in Google search results.';
            }

            feedbackElement.text(message).removeClass('error warning success').addClass(className);
            previewElement.find('.preview-text').text(previewText);
        }

        titleInput.on('keyup input change', updateTitleFeedback);
        updateTitleFeedback();

        $(window).on('beforeunload', function() {
            $measureSpan.remove();
        });
    });
})(jQuery);
