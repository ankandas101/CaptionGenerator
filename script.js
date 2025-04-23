document.addEventListener('DOMContentLoaded', () => {
    const keywordInput = document.getElementById('keyword');
    const generateBtn = document.getElementById('generateBtn');
    const loadingDiv = document.getElementById('loading');
    const resultDiv = document.getElementById('result');
    const captionsContainer = document.getElementById('captionsContainer');
    const copyBtn = document.getElementById('copyBtn');
    const keywordButtons = document.querySelectorAll('.keyword-btn');
    const selectedKeywordsList = document.getElementById('selectedKeywordsList');
    const generateMoreBtn = document.getElementById('generateMoreBtn');

    // Set to store selected keywords
    const selectedKeywords = new Set();
    let currentKeywords = '';
    let generatedCaptions = [];

    // Function to update selected keywords display
    function updateSelectedKeywordsDisplay() {
        if (!selectedKeywordsList) return;
        
        selectedKeywordsList.innerHTML = '';
        selectedKeywords.forEach(keyword => {
            const tag = document.createElement('div');
            tag.className = 'selected-keyword-tag';
            tag.innerHTML = `
                ${keyword}
                <button class="remove-btn" data-keyword="${keyword}">&times;</button>
            `;
            selectedKeywordsList.appendChild(tag);
        });
    }

    // Function to display captions
    function displayCaptions() {
        if (!captionsContainer) return;
        
        captionsContainer.innerHTML = '';
        generatedCaptions.forEach((caption, index) => {
            const captionDiv = document.createElement('div');
            captionDiv.className = 'caption-item';
            captionDiv.innerHTML = `
                <p class="caption-text">${caption}</p>
                <button class="copy-btn" data-index="${index}">Copy</button>
            `;
            captionsContainer.appendChild(captionDiv);
        });

        // Scroll to the last caption if it's a "Generate More" action
        if (generatedCaptions.length > 4) {
            const lastCaption = captionsContainer.lastElementChild;
            if (lastCaption) {
                lastCaption.scrollIntoView({ behavior: 'smooth', block: 'end' });
            }
        }
    }

    // Function to handle caption generation
    async function generateCaptions(isGenerateMore = false) {
        if (!keywordInput || !loadingDiv || !resultDiv || !generateBtn) return;

        const customKeyword = keywordInput.value.trim();
        let allKeywords = Array.from(selectedKeywords);
        
        if (customKeyword) {
            allKeywords.push(customKeyword);
        }
        
        if (allKeywords.length === 0) {
            alert('Please select or enter at least one keyword');
            return;
        }

        currentKeywords = allKeywords.join(', ');

        // Show loading state
        loadingDiv.classList.remove('hidden');
        resultDiv.classList.add('hidden');
        generateBtn.disabled = true;
        if (generateMoreBtn) generateMoreBtn.disabled = true;

        try {
            const response = await fetch('/api/generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ keyword: currentKeywords })
            });

            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Server returned non-JSON response. Please check server configuration.');
            }

            const data = await response.json();
            console.log('API Response:', data);

            if (data.error) {
                throw new Error(data.error);
            }

            if (!data.captions || !Array.isArray(data.captions)) {
                throw new Error('No captions received from the API');
            }

            // Add the new captions to our array
            if (isGenerateMore) {
                generatedCaptions = [...generatedCaptions, ...data.captions];
            } else {
                generatedCaptions = data.captions;
            }
            
            // Display all captions
            displayCaptions();
            loadingDiv.classList.add('hidden');
            resultDiv.classList.remove('hidden');
            if (generateMoreBtn) generateMoreBtn.classList.remove('hidden');

            // Scroll to the new captions
            if (isGenerateMore && captionsContainer) {
                captionsContainer.scrollTo({
                    top: captionsContainer.scrollHeight,
                    behavior: 'smooth'
                });
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error generating captions: ' + error.message);
            loadingDiv.classList.add('hidden');
        } finally {
            generateBtn.disabled = false;
            if (generateMoreBtn) generateMoreBtn.disabled = false;
        }
    }

    // Handle keyword button clicks
    keywordButtons.forEach(button => {
        button.addEventListener('click', () => {
            const keyword = button.dataset.keyword;
            if (selectedKeywords.has(keyword)) {
                selectedKeywords.delete(keyword);
                button.classList.remove('selected');
            } else {
                selectedKeywords.add(keyword);
                button.classList.add('selected');
            }
            updateSelectedKeywordsDisplay();
        });
    });

    // Handle removing keywords from selected list
    if (selectedKeywordsList) {
        selectedKeywordsList.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-btn')) {
                const keyword = e.target.dataset.keyword;
                selectedKeywords.delete(keyword);
                const button = document.querySelector(`.keyword-btn[data-keyword="${keyword}"]`);
                if (button) {
                    button.classList.remove('selected');
                }
                updateSelectedKeywordsDisplay();
            }
        });
    }

    // Add custom keyword when Enter is pressed
    if (keywordInput) {
        keywordInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const customKeyword = keywordInput.value.trim();
                if (customKeyword) {
                    selectedKeywords.add(customKeyword);
                    updateSelectedKeywordsDisplay();
                    keywordInput.value = '';
                }
            }
        });
    }

    // Generate button click handler
    if (generateBtn) {
        generateBtn.addEventListener('click', () => {
            generateCaptions(false);
        });
    }

    // Generate More button click handler
    if (generateMoreBtn) {
        generateMoreBtn.addEventListener('click', () => {
            generateCaptions(true);
        });
    }

    // Handle copy button clicks for individual captions
    if (captionsContainer) {
        captionsContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('copy-btn')) {
                const index = e.target.dataset.index;
                const captionText = e.target.previousElementSibling.textContent;
                navigator.clipboard.writeText(captionText).then(() => {
                    const originalText = e.target.textContent;
                    e.target.textContent = 'Copied!';
                    setTimeout(() => {
                        e.target.textContent = originalText;
                    }, 2000);
                }).catch(err => {
                    console.error('Failed to copy text: ', err);
                });
            }
        });
    }
}); 