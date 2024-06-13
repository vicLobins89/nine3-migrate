(() => {
	const dragstartHandler = function (event) {
		event.dataTransfer.setData('text/plain', event.target.dataset.key);
	}

	const dragoverHandler = function (event) {
		event.dataTransfer.dropEffect = "copy";
	}

	const dropHandler = function (event) {
		const data = event.dataTransfer.getData('text/plain');
 		event.target.value = data;
	}

	const addField = function (event) {
		event.preventDefault();
		const inputField = document.querySelector('#new_meta_field');
		const listWrapper = document.querySelector('.field-map__meta');
		const inputKey = inputField.value;
		inputField.value = '';

		const metaHtml = `<label for="nine3_data[${inputKey}][key]">${inputKey}</label><br>
		<input type="text" name="nine3_data[${inputKey}][key]">
		<select name="nine3_data[${inputKey}][type]">
			<option value="plain">Plain Text/HTML</option>
			<option value="array">Array</option>
			<option value="date">Date</option>
			<option value="slug">Slug</option>
			<option value="image_id">Image ID</option>
			<option value="image_url">Image URL</option>
			<option value="file">File</option>
		</select>`;

		const newDiv = document.createElement('div');
		newDiv.classList.add('field-map__field');
		newDiv.innerHTML = metaHtml;

		listWrapper.appendChild(newDiv);
	}

	const addTaxOption = function (event) {
		const optValue = event.target.value;
		if (optValue === 'term_id') {
			const taxInput = document.createElement('input');
			let taxInputName = event.target.name.slice(0, -6);
			taxInputName += '[tax]';
			taxInput.setAttribute('type', 'text');
			taxInput.setAttribute('name', taxInputName);
			taxInput.setAttribute('placeholder', 'Insert taxonomy name here');
			event.target.parentNode.appendChild(taxInput);
		}
	}

  window.addEventListener('DOMContentLoaded', () => {
		const elements = document.querySelectorAll('.field-map__field[data-key]');
		const addFieldButton = document.querySelector('.add_field');
		const selectElements = document.querySelectorAll('.field-map__field select');

		if (!elements[0]) {
			return;
		}

		elements.forEach((element) => {
			element.addEventListener('dragstart', dragstartHandler);
		});

		addFieldButton.addEventListener('click', addField);

		selectElements.forEach((select) => {
			select.addEventListener('change', addTaxOption);
		});
	});
})();
