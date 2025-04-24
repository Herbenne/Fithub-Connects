/**
 * Handles the multi-step form functionality, file uploads, validation, and submission
 */

document.addEventListener('DOMContentLoaded', function() {
    // Form step navigation elements
    const steps = document.querySelectorAll('.step');
    const formSections = document.querySelectorAll('.form-section');
    const nextButtons = document.querySelectorAll('.next-btn');
    const prevButtons = document.querySelectorAll('.prev-btn');
    
    // Next button click handler
    nextButtons.forEach(button => {
        button.addEventListener('click', function() {
            const nextStep = this.getAttribute('data-next');
            const currentSection = this.closest('.form-section');
            
            // Validate current section first
            if (!validateSection(currentSection.id)) {
                return;
            }
            
            // Hide current section
            currentSection.style.display = 'none';
            
            // Show next section
            document.getElementById(`step-${nextStep}`).style.display = 'block';
            
            // Update step indicators
            updateStepIndicators(nextStep);
            
            // If going to review step, populate summary
            if (nextStep === 'review') {
                populateSummary();
            }
            
            // Scroll to top
            window.scrollTo(0, 0);
        });
    });
    
    // Previous button click handler
    prevButtons.forEach(button => {
        button.addEventListener('click', function() {
            const prevStep = this.getAttribute('data-prev');
            const currentSection = this.closest('.form-section');
            
            // Hide current section
            currentSection.style.display = 'none';
            
            // Show previous section
            document.getElementById(`step-${prevStep}`).style.display = 'block';
            
            // Update step indicators
            updateStepIndicators(prevStep);
            
            // Scroll to top
            window.scrollTo(0, 0);
        });
    });
    
    // Update step indicators
    function updateStepIndicators(activeStep) {
        steps.forEach(step => {
            step.classList.remove('active');
            if (step.getAttribute('data-step') === activeStep) {
                step.classList.add('active');
            }
        });
    }
    
    // Validate section
    function validateSection(sectionId) {
        const section = document.getElementById(sectionId);
        const requiredFields = section.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value) {
                field.classList.add('invalid');
                isValid = false;
                
                // Add error message if not already present
                const errorId = `error-${field.id}`;
                if (!document.getElementById(errorId)) {
                    const errorMsg = document.createElement('p');
                    errorMsg.id = errorId;
                    errorMsg.className = 'error-message';
                    errorMsg.textContent = 'This field is required';
                    errorMsg.style.color = 'red';
                    errorMsg.style.fontSize = '0.85rem';
                    errorMsg.style.marginTop = '0.25rem';
                    field.parentNode.appendChild(errorMsg);
                }
            } else {
                field.classList.remove('invalid');
                const errorMsg = document.getElementById(`error-${field.id}`);
                if (errorMsg) {
                    errorMsg.remove();
                }
            }
        });
        
        return isValid;
    }
    
    // Initialize file upload handlers
    initializeFileUploads();
    
    // Set up form submission validation
    setupFormSubmission();
});

/**
 * Initializes file upload previews for all file inputs
 */
function initializeFileUploads() {
    // Set up file upload previews
    setupFileUpload('gym_thumbnail', 'thumbnailPreview', 'thumbnailDropArea', false);
    setupFileUpload('equipment_images', 'equipmentPreview', 'equipmentDropArea', true);
    setupFileUpload('business_permit', 'permitPreview', 'permitDropArea', false);
    setupFileUpload('valid_id', 'idPreview', 'idDropArea', false);
    setupFileUpload('barangay_clearance', 'barangayPreview', 'barangayDropArea', false);
    setupFileUpload('sanitary_clearance', 'sanitaryPreview', 'sanitaryDropArea', false);
}

/**
 * Sets up file upload functionality for a specific input
 * 
 * @param {string} inputId - ID of the file input element
 * @param {string} previewId - ID of the preview container element
 * @param {string} dropAreaId - ID of the drop area element
 * @param {boolean} multiple - Whether multiple file selection is allowed
 */
function setupFileUpload(inputId, previewId, dropAreaId, multiple) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    const dropArea = document.getElementById(dropAreaId);
    
    if (!input || !preview || !dropArea) return;
    
    // Handle drag and drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        dropArea.classList.add('highlight');
    }
    
    function unhighlight() {
        dropArea.classList.remove('highlight');
    }
    
    // Handle file drop
    dropArea.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (multiple) {
            handleFiles(files);
        } else {
            handleFiles([files[0]]);
        }
    }
    
    // Handle file selection via input
    input.addEventListener('change', function() {
        handleFiles(this.files);
    });
    
    // Click on drop area to trigger file input
    dropArea.addEventListener('click', function() {
        input.click();
    });
    
    // Handle the selected files
    function handleFiles(files) {
        if (!multiple && files.length > 0) {
            // Single file upload
            preview.innerHTML = '';
            previewFile(files[0]);
        } else if (multiple) {
            // Multiple files upload
            for (let i = 0; i < files.length; i++) {
                previewFile(files[i]);
            }
        }
    }
    
    // Generate preview for a file
    function previewFile(file) {
        if (!file) return;
        
        // Check file type
        const fileType = file.type.split('/')[0];
        const previewItem = document.createElement('div');
        previewItem.className = 'file-preview-item';
        
        if (fileType === 'image') {
            // Create image preview
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                previewItem.appendChild(img);
                
                const fileName = document.createElement('div');
                fileName.className = 'file-name';
                fileName.textContent = file.name;
                previewItem.appendChild(fileName);
                
                if (!multiple) {
                    // For single file inputs, replace the message
                    dropArea.querySelector('.file-message').style.display = 'none';
                }
            };
            reader.readAsDataURL(file);
        } else {
            // Create icon for non-image files
            const icon = document.createElement('div');
            icon.className = 'file-type-icon';
            
            // Choose icon based on file type
            if (file.type.includes('pdf')) {
                icon.innerHTML = '<i class="fas fa-file-pdf"></i>';
            } else if (file.type.includes('word') || file.type.includes('document')) {
                icon.innerHTML = '<i class="fas fa-file-word"></i>';
            } else {
                icon.innerHTML = '<i class="fas fa-file"></i>';
            }
            
            previewItem.appendChild(icon);
            
            const fileName = document.createElement('div');
            fileName.className = 'file-name';
            fileName.textContent = file.name;
            previewItem.appendChild(fileName);
            
            if (!multiple) {
                // For single file inputs, replace the message
                dropArea.querySelector('.file-message').style.display = 'none';
            }
        }
        
        // Add remove button for preview
        const removeBtn = document.createElement('div');
        removeBtn.className = 'remove-file';
        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
        removeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            previewItem.remove();
            
            // Reset the file input
            if (!multiple || preview.childElementCount === 0) {
                input.value = '';
                dropArea.querySelector('.file-message').style.display = 'block';
            }
        });
        
        previewItem.appendChild(removeBtn);
        preview.appendChild(previewItem);
    }
}

/**
 * Populates the summary section with form data
 */
function populateSummary() {
    // Basic info summary
    const basicSummary = document.querySelector('#summary-basic .summary-content');
    basicSummary.innerHTML = `
        <p><strong>Gym Name:</strong> ${document.getElementById('gym_name').value}</p>
        <p><strong>Location:</strong> ${document.getElementById('gym_location').value}</p>
        <p><strong>Phone Number:</strong> ${document.getElementById('gym_phone_number').value}</p>
        <p><strong>Description:</strong> ${document.getElementById('gym_description').value}</p>
        <p><strong>Amenities:</strong> ${document.getElementById('gym_amenities').value}</p>
    `;
    
    // Media summary
    const mediaSummary = document.querySelector('#summary-media .summary-content');
    const thumbnailInput = document.getElementById('gym_thumbnail');
    const equipmentInput = document.getElementById('equipment_images');
    
    let mediaContent = '';
    
    if (thumbnailInput.files.length > 0) {
        mediaContent += `<p><strong>Thumbnail:</strong> ${thumbnailInput.files[0].name}</p>`;
    } else {
        mediaContent += '<p><strong>Thumbnail:</strong> None selected</p>';
    }
    
    if (equipmentInput.files.length > 0) {
        mediaContent += `<p><strong>Equipment Images:</strong> ${equipmentInput.files.length} files selected</p>`;
    } else {
        mediaContent += '<p><strong>Equipment Images:</strong> None selected</p>';
    }
    
    mediaSummary.innerHTML = mediaContent;
    
    // Legal documents summary
    const legalSummary = document.querySelector('#summary-legal .summary-content');
    const permitInput = document.getElementById('business_permit');
    const idInput = document.getElementById('valid_id');
    const barangayInput = document.getElementById('barangay_clearance');
    const sanitaryInput = document.getElementById('sanitary_clearance');
    
    let legalContent = '';
    
    if (permitInput.files.length > 0) {
        legalContent += `<p><strong>Business Permit:</strong> ${permitInput.files[0].name}</p>`;
    } else {
        legalContent += '<p><strong>Business Permit:</strong> None selected</p>';
    }
    
    if (idInput.files.length > 0) {
        legalContent += `<p><strong>Valid ID:</strong> ${idInput.files[0].name}</p>`;
    } else {
        legalContent += '<p><strong>Valid ID:</strong> None selected</p>';
    }
    
    if (barangayInput.files.length > 0) {
        legalContent += `<p><strong>Barangay Clearance:</strong> ${barangayInput.files[0].name}</p>`;
    } else {
        legalContent += '<p><strong>Barangay Clearance:</strong> None selected</p>';
    }
    
    if (sanitaryInput.files.length > 0) {
        legalContent += `<p><strong>Sanitary Clearance:</strong> ${sanitaryInput.files[0].name}</p>`;
    } else {
        legalContent += '<p><strong>Sanitary Clearance:</strong> Not provided (optional)</p>';
    }
    
    legalSummary.innerHTML = legalContent;
}

/**
 * Sets up form submission validation
 */
function setupFormSubmission() {
    const form = document.getElementById('gymApplicationForm');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        // Validate all required fields
        const requiredFields = this.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value) {
                isValid = false;
                
                // If it's a file input that's inside a hidden section, show that section first
                if (field.type === 'file') {
                    const section = field.closest('.form-section');
                    const stepId = section.id.replace('step-', '');
                    
                    // Show the section
                    document.querySelectorAll('.form-section').forEach(sec => {
                        sec.style.display = 'none';
                    });
                    section.style.display = 'block';
                    
                    // Update step indicators
                    document.querySelectorAll('.step').forEach(step => {
                        step.classList.remove('active');
                        if (step.getAttribute('data-step') === stepId) {
                            step.classList.add('active');
                        }
                    });
                }
                
                field.focus();
                alert('Please fill out all required fields before submitting.');
                e.preventDefault();
                return false;
            }
        });
        
        // Validate terms agreement
        const termsCheckbox = document.getElementById('terms_agreement');
        if (termsCheckbox && !termsCheckbox.checked) {
            alert('Please agree to the terms and conditions before submitting.');
            e.preventDefault();
            return false;
        }
        
        // If everything is valid, show loading state
        if (isValid) {
            const submitBtn = document.querySelector('.submit-btn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            submitBtn.disabled = true;
        }
    });
}