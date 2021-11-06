import React, { useEffect, useState, useRef } from "react";
//import styles from "./Autocomplete.css";

function Input(props) {
  return (
    
      <input type="text" className="form-control" name={props.name} id={props.name} defaultValue={props.value} placeholder={props.placeholder} />
     
    
  );
};

export default Input;
