(() => {
    'use strict';

    const list = document.getElementById('question-list');
    const addQuestionButton = document.getElementById('add-question');
    const initialDataElement = document.getElementById('initial-question-data');
    if (!list || !addQuestionButton || !initialDataElement) {
        return;
    }

    let initialData;
    try {
        initialData = JSON.parse(initialDataElement.textContent || '[]');
    } catch (error) {
        console.error('Unable to read quiz editor data.', error);
        return;
    }
    let questions = Array.isArray(initialData) && initialData.length
        ? initialData
        : [{ text: '', difficulty: 2, answers: ['', ''], correct: 0, explanation: '' }];

    const difficultiesElement = document.getElementById('difficulty-data');
    let difficulties = [];
    try {
        difficulties = JSON.parse(difficultiesElement?.textContent || '[]');
    } catch (error) {
        console.error('Unable to read difficulty data.', error);
    }

    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function render() {
        list.replaceChildren();
        questions.forEach((question, questionIndex) => {
            const section = element('fieldset', 'member-editor-question');
            section.appendChild(element('legend', '', `Question ${questionIndex + 1}`));

            const textLabel = element('label', 'member-editor-field', 'Intitulé');
            const textInput = element('input');
            textInput.type = 'text';
            textInput.maxLength = 1000;
            textInput.required = true;
            textInput.name = `questions[${questionIndex}][text]`;
            textInput.value = question.text || '';
            textInput.addEventListener('input', () => { question.text = textInput.value; });
            textLabel.appendChild(textInput);
            section.appendChild(textLabel);

            const difficultyLabel = element('label', 'member-editor-field', 'Difficulté');
            const difficultySelect = element('select');
            difficultySelect.name = `questions[${questionIndex}][difficulty]`;
            difficulties.forEach((item) => {
                const option = element('option', '', item.label);
                option.value = String(item.id);
                option.selected = Number(question.difficulty) === Number(item.id);
                difficultySelect.appendChild(option);
            });
            difficultySelect.addEventListener('change', () => { question.difficulty = Number(difficultySelect.value); });
            difficultyLabel.appendChild(difficultySelect);
            section.appendChild(difficultyLabel);

            section.appendChild(element('h3', '', 'Propositions (2 à 5) — sélectionnez la bonne réponse'));
            question.answers = Array.isArray(question.answers) ? question.answers : ['', ''];
            question.answers.forEach((answerText, answerIndex) => {
                const answerRow = element('div', 'member-editor-answer-row');
                const correctLabel = element('label', 'member-editor-correct');
                const correctInput = element('input');
                correctInput.type = 'radio';
                correctInput.name = `questions[${questionIndex}][correct]`;
                correctInput.value = String(answerIndex);
                correctInput.required = true;
                correctInput.checked = Number(question.correct) === answerIndex;
                correctInput.addEventListener('change', () => { question.correct = answerIndex; });
                correctLabel.append(correctInput, document.createTextNode(' Bonne'));

                const answerInput = element('input');
                answerInput.type = 'text';
                answerInput.maxLength = 200;
                answerInput.required = true;
                answerInput.name = `questions[${questionIndex}][answers][${answerIndex}]`;
                answerInput.value = answerText;
                answerInput.addEventListener('input', () => { question.answers[answerIndex] = answerInput.value; });
                answerRow.append(answerInput, correctLabel);

                if (question.answers.length > 2) {
                    const removeAnswer = element('button', 'member-editor-remove', 'Retirer');
                    removeAnswer.type = 'button';
                    removeAnswer.addEventListener('click', () => {
                        question.answers.splice(answerIndex, 1);
                        if (question.correct >= answerIndex) question.correct = Math.max(0, question.correct - 1);
                        render();
                    });
                    answerRow.appendChild(removeAnswer);
                }
                section.appendChild(answerRow);
            });

            const addAnswer = element('button', 'member-editor-add-answer', '＋ Ajouter une proposition');
            addAnswer.type = 'button';
            addAnswer.disabled = question.answers.length >= 5;
            addAnswer.addEventListener('click', () => {
                if (question.answers.length < 5) {
                    question.answers.push('');
                    render();
                }
            });
            section.appendChild(addAnswer);

            const explanationLabel = element('label', 'member-editor-field', 'Explication (facultative)');
            const explanation = element('textarea');
            explanation.name = `questions[${questionIndex}][explanation]`;
            explanation.maxLength = 2000;
            explanation.rows = 2;
            explanation.value = question.explanation || '';
            explanation.addEventListener('input', () => { question.explanation = explanation.value; });
            explanationLabel.appendChild(explanation);
            section.appendChild(explanationLabel);

            if (questions.length > 1) {
                const removeQuestion = element('button', 'member-editor-remove-question', 'Supprimer cette question');
                removeQuestion.type = 'button';
                removeQuestion.addEventListener('click', () => {
                    questions.splice(questionIndex, 1);
                    render();
                });
                section.appendChild(removeQuestion);
            }
            list.appendChild(section);
        });
        addQuestionButton.disabled = questions.length >= 100;
    }

    addQuestionButton.addEventListener('click', () => {
        if (questions.length < 100) {
            questions.push({ text: '', difficulty: difficulties[0]?.id ?? 1, answers: ['', ''], correct: 0, explanation: '' });
            render();
        }
    });
    render();
})();
