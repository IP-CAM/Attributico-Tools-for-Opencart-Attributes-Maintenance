import React, { useEffect, useState, useRef } from "react";
import AsyncCreatableSelect from 'react-select/async-creatable';

const colourOptions =  [
    { value: 'chocolate', label: 'Chocolate' },
    { value: 'strawberry', label: 'Strawberry' },
    { value: 'vanilla', label: 'Vanilla' },
  ];

function AsyncCreateSelect(props) {

    const [inputValue, setValue] = useState('');
    const [selectedValue, setSelectedValue] = useState(null);

    // handle input change event
    const handleInputChange = value => {
        setValue(value);
    };

    // handle selection
    const handleChange = value => {
        setSelectedValue(value);
    }

    const filterColors = (inputValue) => {
        return colourOptions.filter(i =>
            i.label.toLowerCase().includes(inputValue.toLowerCase())
        );
    };

    const promiseOptions = inputValue =>
        new Promise(resolve => {
            setTimeout(() => {
                resolve(filterColors(inputValue));
            }, 1000);
        });

    return (
        <AsyncCreatableSelect
            cacheOptions
            defaultOptions
            loadOptions={promiseOptions}
            id={props.name}
            name={props.name}
            defaultValue={props.defaultValue}
            inputId={props.name}
            inputName={props.name}
            defaultInputValue={props.defaultValue}
            placeholder={props.placeholder}
            form={props.form}
            onInputChange={handleInputChange}
            onChange={handleChange}
        />
    );
}

export default AsyncCreateSelect