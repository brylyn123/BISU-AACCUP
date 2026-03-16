function openReviewModal(docId, filePath, fileName) {
    const modal = document.getElementById('reviewModal');
    const iframe = document.getElementById('docPreview');
    const downloadLink = document.getElementById('modalDownloadLink');
    const title = document.getElementById('modalFileName');
    const docIdInput = document.getElementById('modalDocId');
    const list = document.getElementById('feedbackList');

    // Set Content
    iframe.src = filePath;
    downloadLink.href = filePath;
    title.textContent = fileName;
    docIdInput.value = docId;

    // Show Modal
    modal.classList.remove('hidden');

    // Fetch Feedback
    list.innerHTML = '<div class="flex justify-center py-10"><i class="fas fa-circle-notch fa-spin text-indigo-500 text-2xl"></i></div>';

    fetch(`accreditor_dashboard.php?fetch_feedback=1&doc_id=${docId}`)
        .then(response => response.json())
        .then(data => {
            if (data.length === 0) {
                list.innerHTML = '<div class="text-center text-slate-400 py-10"><i class="far fa-comment-dots text-4xl mb-2 opacity-50"></i><p>No feedback yet.</p></div>';
            } else {
                list.innerHTML = data.map(item => `
                    <div class="bg-white p-3 rounded-lg border border-slate-200 shadow-sm">
                        <div class="flex justify-between items-start mb-1">
                            <span class="font-semibold text-sm text-slate-800">${item.full_name || 'User'}</span>
                            <span class="text-xs text-slate-400">${item.created_at ? new Date(item.created_at).toLocaleDateString() : ''}</span>
                        </div>
                        <div class="text-xs text-indigo-600 mb-2">${item.role_name || 'Accreditor'}</div>
                        <p class="text-sm text-slate-600 leading-relaxed">${item.feedback_text}</p>
                    </div>
                `).join('');
            }
        })
        .catch(err => {
            list.innerHTML = '<div class="text-center text-red-500 py-4">Failed to load comments.</div>';
        });
}

function openSurveyModal(areaName, areaId) {
    const modal = document.getElementById('surveyModal');
    document.getElementById('surveyModalTitle').textContent = areaName;
    document.getElementById('surveyAreaId').value = areaId;

    // Fetch and Render Questions
    const container = document.getElementById('surveyContainer');
    const pdfContainer = document.getElementById('surveyPdfContainer');
    const pdfFrame = document.getElementById('surveyPdfFrame');
    const pdfLink = document.getElementById('surveyPdfLink');
    const programId = document.querySelector('input[name="program_id"]').value;

    container.innerHTML = '<div class="flex justify-center items-center h-full text-slate-400"><i class="fas fa-circle-notch fa-spin mr-2"></i> Loading parameters...</div>';
    pdfContainer.classList.add('hidden'); // Hide PDF initially

    fetch(`accreditor_dashboard.php?fetch_survey_params=1&area_id=${areaId}&program_id=${programId}`)
        .then(res => res.json())
        .then(data => {
            const params = data.params;
            const file = data.file;

            // Handle PDF File
            if (file) {
                pdfFrame.src = file;
                pdfLink.href = file;
                pdfContainer.classList.remove('hidden');
            }

            if (params.length === 0) {
                container.innerHTML = '<div class="text-center text-slate-400 py-10">No parameters defined for this area yet.</div>';
                return;
            }

            let html = '';
            params.forEach(item => {
                html += `
                <div class="p-4 border border-slate-200 rounded-lg bg-white shadow-sm">
                    <p class="font-medium text-slate-700 mb-3">${item.parameter_text}</p>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium text-slate-600">Rating:</label>
                            <div class="flex items-center gap-1">`;

                for (let j = 1; j <= 5; j++) {
                    const checked = (parseInt(item.current_rating) === j) ? 'checked' : '';
                    html += `<label class="flex items-center justify-center w-8 h-8 rounded-full border border-slate-200 cursor-pointer hover:bg-slate-100 has-[:checked]:bg-indigo-500 has-[:checked]:text-white has-[:checked]:border-indigo-500 transition-colors">
                                <input type="radio" name="rating_param_${item.param_id}" value="${j}" class="sr-only" ${checked}>
                                <span class="font-bold text-sm">${j}</span>
                            </label>`;
                }

                html += `   </div>
                        </div>
                    </div>
                </div>`;
            });
            container.innerHTML = html;
        })
        .catch(err => {
            container.innerHTML = '<div class="text-center text-red-500 py-4">Error loading survey parameters.</div>';
        });

    modal.classList.remove('hidden');
}